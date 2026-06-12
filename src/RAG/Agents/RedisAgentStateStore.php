<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Exceptions\SerializationException;
use ML\IDEA\RAG\Contracts\AgentStateStoreInterface;

final class RedisAgentStateStore implements AgentStateStoreInterface
{
    public function __construct(
        private readonly \Redis $redis,
        private readonly string $keyPrefix = 'mlidea:agent:',
        private readonly int $ttlSeconds = 0,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function connect(array $config = []): self
    {
        if (!extension_loaded('redis') && !class_exists(\Redis::class, false)) {
            throw new InvalidArgumentException('The PHP redis extension is required for RedisAgentStateStore.');
        }

        $host = isset($config['host']) ? (string) $config['host'] : '127.0.0.1';
        $port = isset($config['port']) ? (int) $config['port'] : 6379;
        $timeout = isset($config['timeout']) ? (float) $config['timeout'] : 1.5;
        $database = isset($config['database']) ? (int) $config['database'] : 0;
        $prefix = isset($config['prefix']) ? (string) $config['prefix'] : 'mlidea:agent:';
        $ttl = isset($config['ttl']) ? (int) $config['ttl'] : 0;
        $password = isset($config['password']) ? (string) $config['password'] : '';

        $redis = new \Redis();
        if (!$redis->connect($host, $port, $timeout)) {
            throw new SerializationException(sprintf('Unable to connect to Redis at %s:%d', $host, $port));
        }

        if ($password !== '' && !$redis->auth($password)) {
            throw new SerializationException('Redis authentication failed.');
        }

        if ($database > 0 && !$redis->select($database)) {
            throw new SerializationException(sprintf('Unable to select Redis database %d.', $database));
        }

        return new self($redis, $prefix, $ttl);
    }

    public function save(string $sessionId, AgentState $state): void
    {
        $key = $this->keyFor($sessionId);
        $payload = $state->toJson();
        if ($this->redis->set($key, $payload) !== true) {
            throw new SerializationException(sprintf('Failed to write agent state to Redis key: %s', $key));
        }

        if ($this->ttlSeconds > 0) {
            $this->redis->expire($key, $this->ttlSeconds);
        }
    }

    public function load(string $sessionId): ?AgentState
    {
        $raw = $this->redis->get($this->keyFor($sessionId));
        if ($raw === false || !is_string($raw) || $raw === '') {
            return null;
        }

        return AgentState::fromJson($raw);
    }

    public function delete(string $sessionId): void
    {
        $this->redis->del($this->keyFor($sessionId));
    }

    public function exists(string $sessionId): bool
    {
        return (bool) $this->redis->exists($this->keyFor($sessionId));
    }

    private function keyFor(string $sessionId): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', trim($sessionId)) ?? '';
        if ($safe === '' || $safe === '.' || $safe === '..') {
            throw new InvalidArgumentException('Invalid agent state session id.');
        }

        return $this->keyPrefix . $safe;
    }
}
