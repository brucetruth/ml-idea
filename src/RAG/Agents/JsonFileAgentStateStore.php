<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Exceptions\SerializationException;
use ML\IDEA\RAG\Contracts\AgentStateStoreInterface;

final class JsonFileAgentStateStore implements AgentStateStoreInterface
{
    public function __construct(private readonly string $directory)
    {
        if ($this->directory === '') {
            throw new InvalidArgumentException('Agent state directory cannot be empty.');
        }
    }

    public function save(string $sessionId, AgentState $state): void
    {
        $this->ensureDirectory();
        $path = $this->pathFor($sessionId);
        $tmp = $path . '.tmp';

        $bytes = @file_put_contents($tmp, $state->toJson(), LOCK_EX);
        if ($bytes === false) {
            throw new SerializationException(sprintf('Failed to write agent state: %s', $tmp));
        }

        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new SerializationException(sprintf('Failed to move agent state into place: %s', $path));
        }
    }

    public function load(string $sessionId): ?AgentState
    {
        $path = $this->pathFor($sessionId);
        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new SerializationException(sprintf('Failed to read agent state: %s', $path));
        }

        return AgentState::fromJson($raw);
    }

    public function delete(string $sessionId): void
    {
        $path = $this->pathFor($sessionId);
        if (is_file($path) && !@unlink($path)) {
            throw new SerializationException(sprintf('Failed to delete agent state: %s', $path));
        }
    }

    public function exists(string $sessionId): bool
    {
        return is_file($this->pathFor($sessionId));
    }

    private function ensureDirectory(): void
    {
        if (is_dir($this->directory)) {
            return;
        }

        if (!@mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new SerializationException(sprintf('Failed to create agent state directory: %s', $this->directory));
        }
    }

    private function pathFor(string $sessionId): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', trim($sessionId)) ?? '';
        if ($safe === '' || $safe === '.' || $safe === '..') {
            throw new InvalidArgumentException('Invalid agent state session id.');
        }

        return rtrim($this->directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safe . '.json';
    }
}

