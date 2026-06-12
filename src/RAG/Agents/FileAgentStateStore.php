<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

use ML\IDEA\RAG\Contracts\AgentStateStoreInterface;

/**
 * File-backed agent session store (default, zero external dependencies).
 */
final class FileAgentStateStore implements AgentStateStoreInterface
{
    private JsonFileAgentStateStore $inner;

    public function __construct(string $directory)
    {
        $this->inner = new JsonFileAgentStateStore($directory);
    }

    public function save(string $sessionId, AgentState $state): void
    {
        $this->inner->save($sessionId, $state);
    }

    public function load(string $sessionId): ?AgentState
    {
        return $this->inner->load($sessionId);
    }

    public function delete(string $sessionId): void
    {
        $this->inner->delete($sessionId);
    }

    public function exists(string $sessionId): bool
    {
        return $this->inner->exists($sessionId);
    }
}
