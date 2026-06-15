<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\RAG\Embeddings\EmbedderFactory;
use ML\IDEA\RAG\Embeddings\VisionPathEmbedder;
use ML\IDEA\RAG\VectorStore\AnnVectorStore;
use ML\IDEA\RAG\Vision\VisionIndexer;
use ML\IDEA\Vision\Embeddings\ForensicsVisionEmbedder;
use ML\IDEA\Vision\Support\VisionTestImages;

echo "Example 39 - Production wiring (EmbedderFactory, VisionIndexer, env-based providers)\n\n";

// Text embedder from env (default: hash — set RAG_EMBEDDER_PROVIDER=ollama|openai|tei|huggingface)
$textEmbedder = EmbedderFactory::fromEnv(getenv('RAG_EMBEDDER_PROVIDER') ?: 'hash');
$textVector = $textEmbedder->embed('machine learning in php');
echo 'Text embedder: ' . $textEmbedder::class . ' dims=' . count($textVector) . PHP_EOL;

// Image index + search via VisionIndexer
$artifactDir = sys_get_temp_dir() . '/ml-idea-ex39-' . uniqid('', true);
mkdir($artifactDir, 0777, true);

$paths = [
    'sunset-ai' => VisionTestImages::createFlatAiLike($artifactDir . '/sunset-ai.png'),
    'portrait-ai' => VisionTestImages::createFlatAiLike($artifactDir . '/portrait-ai.png'),
    'landscape-real' => VisionTestImages::createTexturedAuthentic($artifactDir . '/landscape-real.png'),
    'street-real' => VisionTestImages::createTexturedAuthentic($artifactDir . '/street-real.png'),
];

$visionEmbedder = new ForensicsVisionEmbedder();
$store = new AnnVectorStore(nlist: 2, nprobe: 2, minItemsForAnn: 4, seed: 42);
$count = VisionIndexer::index($visionEmbedder, $store, $paths, [
    'sunset-ai' => ['kind' => 'ai'],
    'portrait-ai' => ['kind' => 'ai'],
    'landscape-real' => ['kind' => 'authentic'],
    'street-real' => ['kind' => 'authentic'],
]);
echo 'Indexed images: ' . $count . PHP_EOL;

$hits = VisionIndexer::searchByImage($visionEmbedder, $store, $paths['sunset-ai'], k: 2);
echo 'Query sunset-ai → top: ' . ($hits[0]['id'] ?? 'none') . ' score=' . round((float) ($hits[0]['score'] ?? 0), 4) . PHP_EOL;

// Directory scan indexing
$scanStore = new AnnVectorStore(nlist: 2, nprobe: 2, minItemsForAnn: 100, seed: 42);
$scanned = VisionIndexer::indexDirectory(new ForensicsVisionEmbedder(), $scanStore, $artifactDir);
echo 'Directory scan indexed: ' . $scanned . PHP_EOL;

echo 'OllamaVisionEmbedder: ML\\IDEA\\Vision\\Embeddings\\OllamaVisionEmbedder (set OLLAMA_BASE_URL + multimodal model)' . PHP_EOL;
echo 'VisionPathEmbedder bridges image paths into any RAG EmbedderInterface consumer' . PHP_EOL;
