<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Chains;

use ML\IDEA\RAG\Contracts\EmbedderInterface;
use ML\IDEA\RAG\Contracts\LlmClientInterface;
use ML\IDEA\RAG\Contracts\QueryExpanderInterface;
use ML\IDEA\RAG\Contracts\RerankerInterface;
use ML\IDEA\RAG\Contracts\AnswerVerifierInterface;
use ML\IDEA\RAG\Contracts\StreamingLlmClientInterface;
use ML\IDEA\RAG\Contracts\TextSplitterInterface;
use ML\IDEA\RAG\Contracts\VectorStoreInterface;
use ML\IDEA\RAG\Document;
use ML\IDEA\RAG\Prompt\PromptTemplate;
use ML\IDEA\RAG\Rerankers\LexicalOverlapReranker;
use ML\IDEA\RAG\Retriever\SimilarityRetriever;
use ML\IDEA\RAG\Verification\ContextGroundingVerifier;

final class RetrievalQAChain
{
    private SimilarityRetriever $retriever;
    private RerankerInterface $reranker;
    private QueryExpanderInterface $queryExpander;
    private AnswerVerifierInterface $verifier;

    public function __construct(
        private readonly EmbedderInterface $embedder,
        private readonly VectorStoreInterface $vectorStore,
        private readonly TextSplitterInterface $splitter,
        private readonly LlmClientInterface $llm,
        ?RerankerInterface $reranker = null,
        ?QueryExpanderInterface $queryExpander = null,
        ?AnswerVerifierInterface $verifier = null,
    ) {
        $this->retriever = new SimilarityRetriever($embedder, $vectorStore);
        $this->reranker = $reranker ?? new LexicalOverlapReranker();
        $this->queryExpander = $queryExpander ?? new class () implements QueryExpanderInterface {
            public function expand(string $query): array
            {
                return [$query];
            }
        };
        $this->verifier = $verifier ?? new ContextGroundingVerifier();
    }

    /** @param array<int, Document> $documents */
    public function index(array $documents): void
    {
        $chunks = $this->splitter->splitDocuments($documents);
        if ($chunks === []) {
            return;
        }

        $vectors = $this->embedder->embedBatch(array_map(static fn (array $c): string => $c['text'], $chunks));

        $items = [];
        foreach ($chunks as $i => $chunk) {
            $items[] = [
                'id' => $chunk['id'],
                'vector' => $vectors[$i] ?? [],
                'text' => $chunk['text'],
                'metadata' => $chunk['metadata'],
            ];
        }

        $this->vectorStore->upsert($items);
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $llmOptions
     * @return array{answer: string, contexts: array<int, array{id: string, text: string, metadata: array<string, mixed>, score: float}>, citations: array<int, string>, diagnostics: array<string, mixed>, verification: array{is_valid: bool, issues: array<int, string>}}
     */
    public function ask(string $question, int $k = 5, array $filters = [], array $llmOptions = []): array
    {
        $expandedQueries = $this->queryExpander->expand($question);

        $merged = [];
        foreach ($expandedQueries as $q) {
            $hits = $this->retriever->retrieve($q, $k, $filters);
            foreach ($hits as $hit) {
                $id = $hit['id'];
                if (!isset($merged[$id]) || $hit['score'] > $merged[$id]['score']) {
                    $merged[$id] = $hit;
                }
            }
        }

        $contexts = array_values($merged);
        $contexts = $this->reranker->rerank($question, $contexts);
        $contexts = array_slice($contexts, 0, max(1, $k));

        $prompt = PromptTemplate::retrievalQa($question, $contexts);
        $answer = $this->llm->generate($prompt, $llmOptions);

        $verification = $this->verifier->verify($question, $answer, $contexts);
        $citations = array_map(static fn (array $c): string => $c['id'], $contexts);
        $diagnostics = [
            'query_count' => count($expandedQueries),
            'avg_score' => $contexts === [] ? 0.0 : (array_sum(array_map(static fn (array $c): float => (float) $c['score'], $contexts)) / count($contexts)),
            'scores' => array_map(static fn (array $c): float => (float) $c['score'], $contexts),
        ];

        return [
            'answer' => $answer,
            'contexts' => $contexts,
            'citations' => $citations,
            'diagnostics' => $diagnostics,
            'verification' => $verification,
        ];
    }

    /**
     * @return iterable<int, string>
     */
    public function askStream(string $question, int $k = 5, array $filters = [], array $llmOptions = []): iterable
    {
        $contexts = $this->retriever->retrieve($question, $k, $filters);
        $contexts = $this->reranker->rerank($question, $contexts);
        $contexts = array_slice($contexts, 0, max(1, $k));

        $prompt = PromptTemplate::retrievalQa($question, $contexts);

        if ($this->llm instanceof StreamingLlmClientInterface) {
            return $this->llm->streamGenerate($prompt, $llmOptions);
        }

        return [$this->llm->generate($prompt, $llmOptions)];
    }
}
