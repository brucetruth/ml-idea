<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Contracts;

use ML\IDEA\RAG\Agents\AgentRunLogEntry;

interface AgentRunLoggerInterface
{
    public function log(AgentRunLogEntry $entry): void;
}
