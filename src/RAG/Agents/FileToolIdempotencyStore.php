<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

use ML\IDEA\RAG\Contracts\ToolIdempotencyStoreInterface;

final class FileToolIdempotencyStore implements ToolIdempotencyStoreInterface
{
    public function __construct(private readonly string $directory)
    {
    }

    public function has(string $key): bool
    {
        return is_file($this->path($key));
    }

    public function get(string $key): ?array
    {
        if (!$this->has($key)) {
            return null;
        }

        $raw = file_get_contents($this->path($key));
        if ($raw === false || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function put(string $key, array $result): void
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0777, true);
        }

        file_put_contents(
            $this->path($key),
            json_encode($result, JSON_THROW_ON_ERROR),
            LOCK_EX,
        );
    }

    private function path(string $key): string
    {
        return $this->directory . '/' . hash('sha256', $key) . '.json';
    }
}
