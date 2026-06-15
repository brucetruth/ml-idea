<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\RAG\Document;
use ML\IDEA\RAG\Embeddings\HashEmbedder;
use ML\IDEA\RAG\Splitters\RecursiveTextSplitter;
use ML\IDEA\RAG\VectorStore\AnnVectorStore;
use ML\IDEA\RAG\VectorStore\JsonFileVectorStore;
use ML\IDEA\RAG\VectorStore\SQLiteAnnVectorStore;
use ML\IDEA\RAG\VectorStore\SQLiteVectorStore;
use ML\IDEA\RAG\VectorStore\VectorStoreFactory;

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

$annStore = new AnnVectorStore(nlist: 2, nprobe: 2, minItemsForAnn: 100, seed: 42);
$annStore->upsert($items);

$sqliteAnnStore = new SQLiteAnnVectorStore($artifactDir . '/vectors-ann.sqlite', nlist: 2, nprobe: 2, minItemsForAnn: 100, seed: 42);
$sqliteAnnStore->upsert($items);

$vec0Store = VectorStoreFactory::createPersisted($artifactDir . '/vectors-vec0.sqlite', dimensions: count($vectors[0] ?? [16]));
$vec0Store->upsert($items);

$query = 'Which embedder is useful for local private deployments?';
$qv = $embedder->embed($query);

$jsonHit = $jsonStore->search($qv, 1)[0] ?? null;
$sqliteHit = $sqliteStore->search($qv, 1)[0] ?? null;
$annHit = $annStore->search($qv, 1)[0] ?? null;
$sqliteAnnHit = $sqliteAnnStore->search($qv, 1)[0] ?? null;
$vec0Hit = $vec0Store->search($qv, 1)[0] ?? null;

echo "Example 10 - JSON + SQLite + ANN Vector Stores\n";
echo 'JSON top id: ' . json_encode($jsonHit['id'] ?? null, JSON_THROW_ON_ERROR) . PHP_EOL;
echo 'SQLite top id: ' . json_encode($sqliteHit['id'] ?? null, JSON_THROW_ON_ERROR) . PHP_EOL;
echo 'ANN top id: ' . json_encode($annHit['id'] ?? null, JSON_THROW_ON_ERROR) . ' (exact on small corpus)' . PHP_EOL;
echo 'SQLite+ANN top id: ' . json_encode($sqliteAnnHit['id'] ?? null, JSON_THROW_ON_ERROR) . ' (persisted + IVF at scale)' . PHP_EOL;
echo 'VectorStoreFactory top id: ' . json_encode($vec0Hit['id'] ?? null, JSON_THROW_ON_ERROR) . ' (vec0 or ANN fallback)' . PHP_EOL;
