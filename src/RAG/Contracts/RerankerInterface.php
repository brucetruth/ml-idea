<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Contracts;

interface RerankerInterface
{
    /**
     * @param array<int, array{id: string, text: string, metadata: array<string, mixed>, score: float}> $contexts
     * @return array<int, array{id: string, text: string, metadata: array<string, mixed>, score: float}>
     */
    public function rerank(string $query, array $contexts): array;
}
