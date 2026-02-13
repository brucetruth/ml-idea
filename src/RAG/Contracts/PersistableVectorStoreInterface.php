<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Contracts;

interface PersistableVectorStoreInterface extends VectorStoreInterface
{
    /**
     * @return array<int, array{id: string, vector: array<int, float>, text: string, metadata: array<string, mixed>}>
     */
    public function exportItems(): array;

    /**
     * @param array<int, array{id: string, vector: array<int, float>, text: string, metadata?: array<string, mixed>}> $items
     */
    public function importItems(array $items): void;
}
