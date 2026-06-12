<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Contracts;

use ML\IDEA\RAG\Agents\AgentUsage;

interface UsageAwareToolRoutingModelInterface
{
    public function lastUsage(): AgentUsage;
}

