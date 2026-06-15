<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

use ML\IDEA\RAG\Contracts\AgentRunLoggerInterface;

final class JsonlAgentRunLogger implements AgentRunLoggerInterface
{
    public function __construct(private readonly string $path)
    {
    }

    public function log(AgentRunLogEntry $entry): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $line = json_encode($entry->toArray(), JSON_THROW_ON_ERROR) . PHP_EOL;
        file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX);
    }
}
