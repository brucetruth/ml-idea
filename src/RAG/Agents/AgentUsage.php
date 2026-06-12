<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

final class AgentUsage
{
    public function __construct(
        public readonly int $promptTokens = 0,
        public readonly int $completionTokens = 0,
        public readonly int $totalTokens = 0,
        public readonly float $estimatedCost = 0.0,
    ) {
    }

    /** @param array<string, mixed> $usage */
    public static function fromProviderUsage(array $usage, float $costPer1kTokens = 0.0): self
    {
        $prompt = isset($usage['prompt_tokens']) ? (int) $usage['prompt_tokens'] : 0;
        $completion = isset($usage['completion_tokens']) ? (int) $usage['completion_tokens'] : 0;
        $total = isset($usage['total_tokens']) ? (int) $usage['total_tokens'] : ($prompt + $completion);

        return new self($prompt, $completion, $total, ($total / 1000.0) * $costPer1kTokens);
    }

    /** @param array<string, mixed> $usage */
    public static function fromAnthropicUsage(array $usage, float $costPer1kTokens = 0.0): self
    {
        $prompt = isset($usage['input_tokens']) ? (int) $usage['input_tokens'] : 0;
        $completion = isset($usage['output_tokens']) ? (int) $usage['output_tokens'] : 0;
        $total = $prompt + $completion;

        return new self($prompt, $completion, $total, ($total / 1000.0) * $costPer1kTokens);
    }

    public function plus(self $other): self
    {
        return new self(
            $this->promptTokens + $other->promptTokens,
            $this->completionTokens + $other->completionTokens,
            $this->totalTokens + $other->totalTokens,
            $this->estimatedCost + $other->estimatedCost,
        );
    }

    /** @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int, estimated_cost: float} */
    public function toArray(): array
    {
        return [
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->totalTokens,
            'estimated_cost' => $this->estimatedCost,
        ];
    }
}

