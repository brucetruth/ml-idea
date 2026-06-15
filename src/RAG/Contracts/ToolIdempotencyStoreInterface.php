<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Contracts;

interface ToolIdempotencyStoreInterface
{
    public function has(string $key): bool;

    /** @return array<string, mixed>|null */
    public function get(string $key): ?array;

    /** @param array<string, mixed> $result ToolExecutionResult::toArray() shape */
    public function put(string $key, array $result): void;
}
