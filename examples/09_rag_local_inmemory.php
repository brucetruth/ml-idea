<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\RAG\Chains\RetrievalQAChain;
use ML\IDEA\RAG\Document;
use ML\IDEA\RAG\Embeddings\HashEmbedder;
use ML\IDEA\RAG\LLM\EchoLlmClient;
use ML\IDEA\RAG\Splitters\RecursiveTextSplitter;
use ML\IDEA\RAG\VectorStore\InMemoryVectorStore;

$docs = [
    new Document('doc-1', 'Model persistence in ml-idea is handled by ModelSerializer and model save/load helpers.'),
    new Document('doc-2', 'You can run cross validation with KFold, StratifiedKFold, and TimeSeriesSplit.'),
    new Document('doc-3', 'Calibration tools include CalibratedClassifierCV and ThresholdTuner for probability workflows.'),
];

$chain = new RetrievalQAChain(
    new HashEmbedder(24),
    new InMemoryVectorStore(),
    new RecursiveTextSplitter(chunkSize: 120, chunkOverlap: 20),
    new EchoLlmClient(),
);

$chain->index($docs);
$result = $chain->ask('How do I persist models in this library?', k: 3);

echo "Example 09 - Local RAG (InMemory)\n";
echo 'Answer: ' . $result['answer'] . PHP_EOL;
echo 'Top context IDs: ' . json_encode(array_map(static fn ($c) => $c['id'], $result['contexts']), JSON_THROW_ON_ERROR) . PHP_EOL;
