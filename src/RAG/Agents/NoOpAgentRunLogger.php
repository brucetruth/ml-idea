<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

use ML\IDEA\RAG\Contracts\AgentRunLoggerInterface;

final class NoOpAgentRunLogger implements AgentRunLoggerInterface
{
    public function log(AgentRunLogEntry $entry): void
    {
    }
}
