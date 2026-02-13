<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\RAG\Document;
use ML\IDEA\RAG\Embeddings\HashEmbedder;
use ML\IDEA\RAG\Splitters\RecursiveTextSplitter;
use ML\IDEA\RAG\VectorStore\JsonFileVectorStore;
use ML\IDEA\RAG\VectorStore\SQLiteVectorStore;

$documents = [
    new Document('d1', 'OpenAI and Azure OpenAI embedders can be swapped behind a shared interface.'),
    new Document('d2', 'Ollama embedder enables local embeddings for offline/private deployments.'),
];

$splitter = new RecursiveTextSplitter(chunkSize: 100, chunkOverlap: 10);
$embedder = new HashEmbedder(16);
$chunks = $splitter->splitDocuments($documents);
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

$artifactDir = __DIR__ . '/artifacts/rag';
if (!is_dir($artifactDir)) {
    mkdir($artifactDir, 0777, true);
}

$jsonStore = new JsonFileVectorStore($artifactDir . '/vectors.json');
$jsonStore->upsert($items);

$sqliteStore = new SQLiteVectorStore($artifactDir . '/vectors.sqlite');
$sqliteStore->upsert($items);

$query = 'Which embedder is useful for local private deployments?';
$qv = $embedder->embed($query);

$jsonHit = $jsonStore->search($qv, 1)[0] ?? null;
$sqliteHit = $sqliteStore->search($qv, 1)[0] ?? null;

echo "Example 10 - JSON + SQLite Vector Stores\n";
echo 'JSON top id: ' . json_encode($jsonHit['id'] ?? null, JSON_THROW_ON_ERROR) . PHP_EOL;
echo 'SQLite top id: ' . json_encode($sqliteHit['id'] ?? null, JSON_THROW_ON_ERROR) . PHP_EOL;
