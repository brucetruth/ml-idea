<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

final class ToolCircuitBreaker
{
    /** @var array<string, int> */
    private array $failures = [];

    /** @var array<string, int> */
    private array $openedAt = [];

    public function __construct(
        private readonly int $failureThreshold = 3,
        private readonly int $cooldownSeconds = 60,
    ) {
    }

    public function isOpen(string $toolName): bool
    {
        if (!isset($this->openedAt[$toolName])) {
            return false;
        }

        if (time() - $this->openedAt[$toolName] >= $this->cooldownSeconds) {
            unset($this->openedAt[$toolName], $this->failures[$toolName]);

            return false;
        }

        return true;
    }

    public function recordSuccess(string $toolName): void
    {
        unset($this->failures[$toolName], $this->openedAt[$toolName]);
    }

    public function recordFailure(string $toolName): void
    {
        $this->failures[$toolName] = ($this->failures[$toolName] ?? 0) + 1;
        if ($this->failures[$toolName] >= $this->failureThreshold) {
            $this->openedAt[$toolName] = time();
        }
    }

    public function failureCount(string $toolName): int
    {
        return $this->failures[$toolName] ?? 0;
    }
}
