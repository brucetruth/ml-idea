<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Contracts;

use ML\IDEA\RAG\Agents\AgentState;

interface AgentStateStoreInterface
{
    public function save(string $sessionId, AgentState $state): void;

    public function load(string $sessionId): ?AgentState;

    public function delete(string $sessionId): void;

    public function exists(string $sessionId): bool;
}

