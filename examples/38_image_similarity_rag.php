<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\RAG\Embeddings\VisionPathEmbedder;
use ML\IDEA\RAG\VectorStore\AnnVectorStore;
use ML\IDEA\Vision\Embeddings\ForensicsVisionEmbedder;
use ML\IDEA\Vision\Support\VisionTestImages;

echo "Example 38 - Image similarity search (forensics embeddings + ANN vector store)\n\n";

$artifactDir = sys_get_temp_dir() . '/ml-idea-vision-rag-' . uniqid('', true);
mkdir($artifactDir, 0777, true);

$paths = [
    'ai-1' => $artifactDir . '/ai-flat-1.png',
    'ai-2' => $artifactDir . '/ai-flat-2.png',
    'real-1' => $artifactDir . '/real-texture-1.png',
    'real-2' => $artifactDir . '/real-texture-2.png',
];

VisionTestImages::createFlatAiLike($paths['ai-1']);
VisionTestImages::createFlatAiLike($paths['ai-2']);
VisionTestImages::createTexturedAuthentic($paths['real-1']);
VisionTestImages::createTexturedAuthentic($paths['real-2']);

$embedder = new VisionPathEmbedder(new ForensicsVisionEmbedder());
$store = new AnnVectorStore(nlist: 2, nprobe: 2, minItemsForAnn: 4, seed: 42);

$items = [];
foreach ($paths as $label => $path) {
    $items[] = [
        'id' => $label,
        'vector' => $embedder->embed($path),
        'text' => basename($path),
        'metadata' => ['kind' => str_starts_with($label, 'ai') ? 'ai' : 'authentic'],
    ];
}
$store->upsert($items);

$queryPath = $paths['ai-1'];
$hits = $store->search($embedder->embed($queryPath), k: 3);

echo 'Query: ' . basename($queryPath) . PHP_EOL;
foreach ($hits as $i => $hit) {
    echo sprintf("  #%d %s score=%.4f kind=%s\n", $i + 1, $hit['id'], $hit['score'], $hit['metadata']['kind'] ?? '?');
}

echo PHP_EOL . 'Top match kind: ' . ($hits[0]['metadata']['kind'] ?? 'none') . PHP_EOL;
