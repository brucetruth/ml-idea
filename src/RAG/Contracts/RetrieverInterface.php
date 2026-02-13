<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Contracts;

interface RetrieverInterface
{
    /**
     * @return array<int, array{id: string, text: string, metadata: array<string, mixed>, score: float}>
     */
    public function retrieve(string $query, int $k = 5, array $filters = []): array;
}
