<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

final class AgentEvalCase
{
    /**
     * @param array<string, mixed> $expect
     */
    public function __construct(
        public readonly string $name,
        public readonly string $prompt,
        public readonly array $expect = [],
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            isset($payload['name']) ? (string) $payload['name'] : 'case',
            isset($payload['prompt']) ? (string) $payload['prompt'] : '',
            isset($payload['expect']) && is_array($payload['expect']) ? $payload['expect'] : [],
        );
    }
}
