<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\RAG\Embeddings\EmbedderFactory;
use ML\IDEA\RAG\Embeddings\HashEmbedder;
use ML\IDEA\RAG\Embeddings\OllamaEmbedder;
use PHPUnit\Framework\TestCase;

final class EmbedderFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('RAG_EMBEDDER_PROVIDER');
        putenv('RAG_HASH_EMBED_DIMS');
        parent::tearDown();
    }

    public function testDefaultsToHashEmbedder(): void
    {
        putenv('RAG_EMBEDDER_PROVIDER');
        $embedder = EmbedderFactory::fromEnv();
        self::assertInstanceOf(HashEmbedder::class, $embedder);
    }

    public function testSelectsOllamaFromEnv(): void
    {
        putenv('RAG_EMBEDDER_PROVIDER=ollama');
        $embedder = EmbedderFactory::fromEnv();
        self::assertInstanceOf(OllamaEmbedder::class, $embedder);
    }

    public function testCustomHashDimensions(): void
    {
        putenv('RAG_EMBEDDER_PROVIDER=hash');
        putenv('RAG_HASH_EMBED_DIMS=32');
        $embedder = EmbedderFactory::fromEnv();
        self::assertInstanceOf(HashEmbedder::class, $embedder);
        self::assertCount(32, $embedder->embed('hello'));
    }
}
