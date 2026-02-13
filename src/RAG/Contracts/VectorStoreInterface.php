<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Contracts;

interface VectorStoreInterface
{
    /**
     * @param array<int, array{id: string, vector: array<int, float>, text: string, metadata?: array<string, mixed>}> $items
     */
    public function upsert(array $items): void;

    /**
     * @param array<int, float> $queryVector
     * @param array<string, mixed> $filters
     * @return array<int, array{id: string, vector: array<int, float>, text: string, metadata: array<string, mixed>, score: float}>
     */
    public function search(array $queryVector, int $k = 5, array $filters = []): array;
}
