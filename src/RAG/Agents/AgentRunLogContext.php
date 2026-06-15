<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

final class AgentRunLogContext
{
    public function __construct(
        public readonly ?string $sessionId = null,
        public readonly ?string $userMessage = null,
        public readonly bool $resume = false,
    ) {
    }
}
