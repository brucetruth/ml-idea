<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

use ML\IDEA\RAG\Contracts\AgentSpanScopeInterface;

final class NoOpAgentSpanScope implements AgentSpanScopeInterface
{
    public function spanId(): string
    {
        return '';
    }

    public function setAttribute(string $key, mixed $value): void
    {
    }

    public function end(string $status = 'ok', ?\Throwable $error = null): void
    {
    }
}
