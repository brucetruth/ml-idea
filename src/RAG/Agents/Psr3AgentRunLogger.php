<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

use ML\IDEA\RAG\Contracts\AgentRunLoggerInterface;
use Psr\Log\LoggerInterface;

final class Psr3AgentRunLogger implements AgentRunLoggerInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $message = 'agent.run.completed',
        private readonly string $level = 'info',
    ) {
    }

    public function log(AgentRunLogEntry $entry): void
    {
        $context = $entry->toArray();
        $this->logger->log($this->level, $this->message, $context);
    }
}
