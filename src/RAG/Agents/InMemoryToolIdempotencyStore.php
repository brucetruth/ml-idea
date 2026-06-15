<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

use ML\IDEA\RAG\Contracts\ToolIdempotencyStoreInterface;

final class InMemoryToolIdempotencyStore implements ToolIdempotencyStoreInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $records = [];

    public function has(string $key): bool
    {
        return isset($this->records[$key]);
    }

    public function get(string $key): ?array
    {
        return $this->records[$key] ?? null;
    }

    public function put(string $key, array $result): void
    {
        $this->records[$key] = $result;
    }
}
