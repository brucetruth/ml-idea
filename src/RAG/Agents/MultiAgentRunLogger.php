<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

use ML\IDEA\RAG\Contracts\AgentRunLoggerInterface;

final class MultiAgentRunLogger implements AgentRunLoggerInterface
{
    /** @param array<int, AgentRunLoggerInterface> $loggers */
    public function __construct(private readonly array $loggers)
    {
    }

    public function log(AgentRunLogEntry $entry): void
    {
        foreach ($this->loggers as $logger) {
            $logger->log($entry);
        }
    }
}
