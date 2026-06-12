<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

final class AgentStreamEvent
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly string $type,
        public readonly array $data = [],
    ) {
    }

    /** @param array<string, mixed> $result */
    public static function final(array $result): self
    {
        return new self('final', ['result' => $result]);
    }

    /** @return array{type: string, data: array<string, mixed>} */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'data' => $this->data,
        ];
    }
}
