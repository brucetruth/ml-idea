<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\RAG\Contracts\AgentStateStoreInterface;

final class AgentStateStoreFactory
{
    /**
     * Create an agent state store.
     *
     * Config keys:
     * - driver: `file` | `redis` | `auto` (default `auto`)
     * - path: directory for file store (default: sys temp `/mlidea_agent_state`)
     * - redis: connection array for {@see RedisAgentStateStore::connect()}
     *
     * `auto` tries Redis when `redis` config is present, otherwise uses the file store.
     * On Redis connection failure in `auto` mode, falls back to file storage.
     *
     * @param array<string, mixed> $config
     */
    public static function create(array $config = []): AgentStateStoreInterface
    {
        $driver = isset($config['driver']) ? (string) $config['driver'] : 'auto';
        $path = isset($config['path']) ? (string) $config['path'] : (sys_get_temp_dir() . '/mlidea_agent_state');

        if ($driver === 'file') {
            return new FileAgentStateStore($path);
        }

        if ($driver === 'redis') {
            /** @var array<string, mixed> $redisConfig */
            $redisConfig = isset($config['redis']) && is_array($config['redis']) ? $config['redis'] : [];
            return RedisAgentStateStore::connect($redisConfig);
        }

        if ($driver !== 'auto') {
            throw new InvalidArgumentException(sprintf('Unsupported agent state store driver: %s', $driver));
        }

        if (isset($config['redis']) && is_array($config['redis'])) {
            try {
                return RedisAgentStateStore::connect($config['redis']);
            } catch (\Throwable) {
                return new FileAgentStateStore($path);
            }
        }

        return new FileAgentStateStore($path);
    }
}
