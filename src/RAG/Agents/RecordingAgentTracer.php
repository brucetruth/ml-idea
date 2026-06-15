<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

use ML\IDEA\RAG\Contracts\AgentSpanScopeInterface;
use ML\IDEA\RAG\Contracts\AgentTracerInterface;

final class RecordingAgentTracer implements AgentTracerInterface
{
    private ?string $traceId = null;

    private ?string $rootSpanId = null;

    /** @var array<int, array<string, mixed>> */
    private array $spans = [];

    public function traceContext(): AgentTraceContext
    {
        if ($this->traceId === null || $this->rootSpanId === null) {
            return new AgentTraceContext();
        }

        return new AgentTraceContext($this->traceId, $this->rootSpanId);
    }

    /** @return array<int, array<string, mixed>> */
    public function spans(): array
    {
        return $this->spans;
    }

    public function startSpan(string $name, array $attributes = [], ?AgentSpanScopeInterface $parent = null): AgentSpanScopeInterface
    {
        if ($this->traceId === null) {
            $this->traceId = bin2hex(random_bytes(16));
        }

        $spanId = bin2hex(random_bytes(8));
        if ($parent === null) {
            $this->rootSpanId = $spanId;
        }

        return new RecordingAgentSpanScope(
            $this,
            $name,
            $spanId,
            $parent instanceof RecordingAgentSpanScope ? $parent->spanId() : null,
            $attributes,
        );
    }

    /** @param array<string, mixed> $record */
    public function recordEnded(array $record): void
    {
        $this->spans[] = $record;
    }
}
