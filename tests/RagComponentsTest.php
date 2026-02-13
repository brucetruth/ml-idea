<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\RAG\Chains\RetrievalQAChain;
use ML\IDEA\RAG\Document;
use ML\IDEA\RAG\Embeddings\HashEmbedder;
use ML\IDEA\RAG\LLM\EchoLlmClient;
use ML\IDEA\RAG\Splitters\RecursiveTextSplitter;
use ML\IDEA\RAG\VectorStore\InMemoryVectorStore;
use PHPUnit\Framework\TestCase;

final class RagComponentsTest extends TestCase
{
    public function testRecursiveSplitterAndInMemorySearchWork(): void
    {
        $splitter = new RecursiveTextSplitter(40, 10);
        $docs = [new Document('d1', 'hello world this is a long enough sentence for chunking tests')];
        $chunks = $splitter->splitDocuments($docs);

        self::assertNotEmpty($chunks);
        self::assertArrayHasKey('id', $chunks[0]);
        self::assertArrayHasKey('text', $chunks[0]);

        $embedder = new HashEmbedder(12);
        $store = new InMemoryVectorStore();

        $vectors = $embedder->embedBatch(array_map(static fn (array $c): string => $c['text'], $chunks));
        $items = [];
        foreach ($chunks as $i => $chunk) {
            $items[] = [
                'id' => $chunk['id'],
                'vector' => $vectors[$i],
                'text' => $chunk['text'],
                'metadata' => $chunk['metadata'],
            ];
        }

        $store->upsert($items);
        $hits = $store->search($embedder->embed('hello world question'), 3);
        self::assertNotEmpty($hits);
    }

    public function testRetrievalQaChainReturnsAnswerAndContexts(): void
    {
        $chain = new RetrievalQAChain(
            new HashEmbedder(16),
            new InMemoryVectorStore(),
            new RecursiveTextSplitter(100, 20),
            new EchoLlmClient(),
        );

        $chain->index([
            new Document('doc-1', 'Model persistence can be achieved with ModelSerializer and save/load.'),
            new Document('doc-2', 'Use KFold and CrossValidation for robust evaluation.'),
        ]);

        $result = $chain->ask('How do we save models?', 2);
        self::assertArrayHasKey('answer', $result);
        self::assertArrayHasKey('contexts', $result);
        self::assertNotEmpty($result['contexts']);
    }
}
