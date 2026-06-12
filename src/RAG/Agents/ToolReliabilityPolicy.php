<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

final class ToolReliabilityPolicy
{
    public function __construct(
        public readonly int $maxAttempts = 1,
        public readonly int $retryDelayMs = 0,
        public readonly int $timeoutMs = 30000,
    ) {
        if ($this->maxAttempts < 1) {
            throw new \InvalidArgumentException('maxAttempts must be at least 1.');
        }
        if ($this->retryDelayMs < 0 || $this->timeoutMs < 0) {
            throw new \InvalidArgumentException('retryDelayMs and timeoutMs must be non-negative.');
        }
    }

    public static function default(): self
    {
        return new self();
    }
}
