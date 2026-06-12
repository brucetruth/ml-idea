<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

final class AgentBudget
{
    public function __construct(
        public readonly int $maxIterations = 8,
        public readonly int $maxToolCalls = 16,
        public readonly int $maxRuntimeMs = 30000,
        public readonly int $maxTokens = 0,
        public readonly float $maxEstimatedCost = 0.0,
    ) {
    }

    public function exceededRuntime(float $startedAt): bool
    {
        return $this->elapsedMs($startedAt) > $this->maxRuntimeMs;
    }

    public function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    public function exceededUsage(AgentUsage $usage): ?string
    {
        if ($this->maxTokens > 0 && $usage->totalTokens > $this->maxTokens) {
            return 'token_budget_exceeded';
        }

        if ($this->maxEstimatedCost > 0.0 && $usage->estimatedCost > $this->maxEstimatedCost) {
            return 'cost_budget_exceeded';
        }

        return null;
    }
}
