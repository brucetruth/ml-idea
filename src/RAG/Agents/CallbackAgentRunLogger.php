<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

use ML\IDEA\RAG\Contracts\AgentRunLoggerInterface;

final class CallbackAgentRunLogger implements AgentRunLoggerInterface
{
    /** @param callable(AgentRunLogEntry): void $callback */
    public function __construct(private readonly mixed $callback)
    {
        if (!is_callable($this->callback)) {
            throw new \InvalidArgumentException('CallbackAgentRunLogger requires a callable.');
        }
    }

    public function log(AgentRunLogEntry $entry): void
    {
        ($this->callback)($entry);
    }
}
