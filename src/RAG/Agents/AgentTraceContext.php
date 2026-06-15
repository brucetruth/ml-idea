<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

final class AgentTraceContext
{
    public function __construct(
        public readonly string $traceId = '',
        public readonly string $spanId = '',
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->traceId === '';
    }

    /** @return array{trace_id: string, span_id: string} */
    public function toArray(): array
    {
        return [
            'trace_id' => $this->traceId,
            'span_id' => $this->spanId,
        ];
    }
}
