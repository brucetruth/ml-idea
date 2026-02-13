<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Persistence;

use ML\IDEA\Exceptions\SerializationException;
use ML\IDEA\RAG\Contracts\PersistableVectorStoreInterface;

final class VectorIndexPersistence
{
    public static function save(PersistableVectorStoreInterface $store, string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $json = json_encode($store->exportItems(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        if (file_put_contents($path, $json) === false) {
            throw new SerializationException(sprintf('Failed to save vector index to %s', $path));
        }
    }

    public static function load(PersistableVectorStoreInterface $store, string $path): void
    {
        if (!is_file($path)) {
            throw new SerializationException(sprintf('Vector index file not found: %s', $path));
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new SerializationException(sprintf('Failed reading vector index: %s', $path));
        }

        /** @var array<int, array{id: string, vector: array<int, float>, text: string, metadata?: array<string, mixed>}> $items */
        $items = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $store->importItems($items);
    }
}
