<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Embeddings;

use ML\IDEA\NLP\Contracts\EmbeddingProviderInterface;
use ML\IDEA\RAG\Contracts\EmbedderInterface;

/**
 * Bridges NLP {@see EmbeddingProviderInterface} into RAG {@see EmbedderInterface}.
 *
 * Use {@see HashingEmbeddingProvider} (NLP pipeline, similarity, clustering) when you
 * want configurable hashing-vector semantics and shared tokenization with NLP tools.
 * Use {@see HashEmbedder} directly for lightweight RAG demos/tests where a smaller,
 * self-contained MD5 hash embedder is enough.
 */
final class EmbeddingProviderAdapter implements EmbedderInterface
{
    public function __construct(private readonly EmbeddingProviderInterface $provider)
    {
    }

    public function embed(string $text): array
    {
        return $this->provider->embed($text);
    }

    public function embedBatch(array $texts): array
    {
        return array_map(fn (string $text): array => $this->provider->embed($text), $texts);
    }

    public function provider(): EmbeddingProviderInterface
    {
        return $this->provider;
    }
}
