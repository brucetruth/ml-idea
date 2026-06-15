<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

use ML\IDEA\RAG\Contracts\AgentSpanScopeInterface;
use ML\IDEA\RAG\Contracts\AgentTracerInterface;

final class NoOpAgentTracer implements AgentTracerInterface
{
    public function traceContext(): AgentTraceContext
    {
        return new AgentTraceContext();
    }

    public function startSpan(string $name, array $attributes = [], ?AgentSpanScopeInterface $parent = null): AgentSpanScopeInterface
    {
        return new NoOpAgentSpanScope();
    }
}
