<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\RAG\Embeddings\HuggingFaceEmbedder;
use ML\IDEA\RAG\Embeddings\TeiEmbedder;
use ML\IDEA\RAG\VectorStore\SQLiteVec0VectorStore;
use ML\IDEA\RAG\VectorStore\VectorStoreFactory;
use ML\IDEA\Vision\Backends\CallableVisionBackend;
use ML\IDEA\Vision\Backends\CallableVisionEmbedder;
use ML\IDEA\Vision\Classifiers\AuthenticityClassifier;
use ML\IDEA\Vision\Features\ImageForensicsFeatureExtractor;

echo "Example 37 - Vision/RAG frontier hooks (backends, HF embedder, vec0 factory)\n\n";

// Vision feature backend hook (ViT/CLIP/custom — inject neural signals here)
$backend = new CallableVisionBackend(
    static fn (array $signals, string $path): array => array_merge($signals, [
        'neural_hook_score' => str_contains(strtolower($path), 'ai') ? 0.9 : 0.1,
    ]),
);
echo 'CallableVisionBackend: ' . CallableVisionBackend::class . PHP_EOL;

// Vision embedder hook (image similarity / RAG over images)
$embedder = new CallableVisionEmbedder(
    static fn (string $path): array => [strlen($path) * 0.01, crc32(basename($path)) % 100 / 100.0],
);
echo 'CallableVisionEmbedder dims: ' . count($embedder->embedImage('/tmp/photo.jpg')) . PHP_EOL;

// HuggingFace text embeddings for RAG (remote API or local TEI — set HF_API_TOKEN)
echo 'HuggingFaceEmbedder: ' . HuggingFaceEmbedder::class . PHP_EOL;
echo 'TeiEmbedder: ' . TeiEmbedder::class . ' (local OpenAI-compatible /v1/embeddings)' . PHP_EOL;

// Persisted vector store: sqlite-vec when extension loaded, else SQLiteAnnVectorStore IVF fallback
$artifactDir = __DIR__ . '/artifacts/rag';
if (!is_dir($artifactDir)) {
    mkdir($artifactDir, 0777, true);
}
$store = VectorStoreFactory::createPersisted($artifactDir . '/vectors-vec0.sqlite', dimensions: 16);
echo 'VectorStoreFactory → ' . ($store instanceof SQLiteVec0VectorStore && $store->usesVec0() ? 'sqlite-vec (vec0)' : 'SQLiteAnnVectorStore fallback') . PHP_EOL;

// AuthenticityClassifier with optional backend wired through forensics extractor
$extractor = new ImageForensicsFeatureExtractor($backend);
$classifier = new AuthenticityClassifier(features: $extractor);
echo 'AuthenticityClassifier + VisionFeatureBackend wired via ImageForensicsFeatureExtractor' . PHP_EOL;
