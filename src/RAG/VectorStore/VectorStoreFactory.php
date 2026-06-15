<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\VectorStore;

use ML\IDEA\RAG\Contracts\PersistableVectorStoreInterface;
use ML\IDEA\RAG\Contracts\VectorStoreInterface;

/** Factory for persisted vector stores with sqlite-vec when available. */
final class VectorStoreFactory
{
    public static function createPersisted(
        string $path,
        int $dimensions = 384,
        int $nlist = 8,
        int $nprobe = 2,
        int $minItemsForAnn = 16,
        ?int $seed = 42,
    ): PersistableVectorStoreInterface {
        return new SQLiteVec0VectorStore($path, $dimensions, $nlist, $nprobe, $minItemsForAnn, $seed);
    }

    public static function create(string $path, int $dimensions = 384): VectorStoreInterface
    {
        return self::createPersisted($path, $dimensions);
    }
}
