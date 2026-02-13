<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Tools;

final class DbQueryAuditLogger
{
    /**
     * @param array<string, mixed> $event
     */
    public static function append(string $path, array $event): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $line = json_encode($event, JSON_THROW_ON_ERROR) . PHP_EOL;
        file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    }
}
