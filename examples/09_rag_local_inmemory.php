<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\NLP\Lexicon\WordNetLexicon;
use ML\IDEA\RAG\Chains\RetrievalQAChain;
use ML\IDEA\RAG\Contracts\LlmClientInterface;
use ML\IDEA\RAG\Document;
use ML\IDEA\RAG\Embeddings\HashEmbedder;
use ML\IDEA\RAG\LLM\LlmClientFactory;
use ML\IDEA\RAG\QueryExpansion\WordNetQueryExpander;
use ML\IDEA\RAG\Splitters\RecursiveTextSplitter;
use ML\IDEA\RAG\VectorStore\AnnVectorStore;
use ML\IDEA\RAG\VectorStore\InMemoryVectorStore;

$docs = [
    new Document('doc-1', 'Model persistence in ml-idea is handled by ModelSerializer and model save/load helpers.'),
    new Document('doc-2', 'You can run cross validation with KFold, StratifiedKFold, and TimeSeriesSplit.'),
    new Document('doc-3', 'Calibration tools include CalibratedClassifierCV and ThresholdTuner for probability workflows.'),
];

// Default local LLM stub for deterministic demos.
// Set RAG_LLM_PROVIDER=openai|azure|ollama|echo to switch providers.
/** @var LlmClientInterface $llm */
$llm = LlmClientFactory::fromEnv();

// WordNet query expansion: adds synonyms + lightweight paraphrases before retrieval.
// Uses full bundled lexicon from src/Dataset/wordnet/wn.json (via DatasetPaths).
// For a faster smoke test: WORDNET_PATH=src/datasets/wordnet/wn.json php examples/09_rag_local_inmemory.php
$wordnetPath = getenv('WORDNET_PATH');
$expander = is_string($wordnetPath) && $wordnetPath !== ''
    ? WordNetQueryExpander::fromLexicon(new WordNetLexicon($wordnetPath), maxQueries: 6)
    : new WordNetQueryExpander(maxQueries: 6);

$question = 'How do I persist models in this library?';

// AnnVectorStore falls back to exact search on small corpora; switches to IVF ANN at scale.
$useAnn = (getenv('RAG_USE_ANN') ?: '1') !== '0';
$vectorStore = $useAnn
    ? new AnnVectorStore(nlist: 4, nprobe: 2, minItemsForAnn: 16, seed: 42)
    : new InMemoryVectorStore();

$chain = new RetrievalQAChain(
    new HashEmbedder(24),
    $vectorStore,
    new RecursiveTextSplitter(chunkSize: 120, chunkOverlap: 20),
    $llm,
    queryExpander: $expander,
);

$chain->index($docs);
$result = $chain->ask($question, k: 3);

echo "Example 09 - Local RAG (InMemory) + WordNet expansion\n";
echo 'Vector store: ' . ($useAnn ? 'AnnVectorStore (exact fallback on small corpus)' : 'InMemoryVectorStore') . PHP_EOL;
echo 'Question: ' . $question . PHP_EOL;
$expanded = $result['diagnostics']['expanded_queries'] ?? [];
echo 'Expanded queries (' . count($expanded) . '): ' . implode(' | ', $expanded) . PHP_EOL;
echo 'Answer: ' . $result['answer'] . PHP_EOL;
echo 'Top context IDs: ' . json_encode(array_map(static fn ($c) => $c['id'], $result['contexts']), JSON_THROW_ON_ERROR) . PHP_EOL;
echo 'Retrieval diagnostics: ' . json_encode($result['diagnostics'], JSON_THROW_ON_ERROR) . PHP_EOL;
