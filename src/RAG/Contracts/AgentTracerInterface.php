<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Contracts;

use ML\IDEA\RAG\Agents\AgentTraceContext;

interface AgentTracerInterface
{
    public function traceContext(): AgentTraceContext;

    /**
     * @param array<string, bool|float|int|string|null> $attributes
     */
    public function startSpan(
        string $name,
        array $attributes = [],
        ?AgentSpanScopeInterface $parent = null,
    ): AgentSpanScopeInterface;
}
