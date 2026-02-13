<?php

declare(strict_types=1);

namespace ML\IDEA\Dataset\Registry;

final class DatasetCache
{
    /** @var array<string, mixed> */
    private static array $memory = [];

    public function __construct(private readonly ?string $cacheDir = null)
    {
    }

    public function has(string $key): bool
    {
        if (array_key_exists($key, self::$memory)) {
            return true;
        }
        $path = $this->pathFor($key);
        return $path !== null && is_file($path);
    }

    public function get(string $key): mixed
    {
        if (array_key_exists($key, self::$memory)) {
            return self::$memory[$key];
        }

        $path = $this->pathFor($key);
        if ($path === null || !is_file($path)) {
            return null;
        }

        $value = @unserialize((string) file_get_contents($path));
        self::$memory[$key] = $value;
        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        self::$memory[$key] = $value;

        $path = $this->pathFor($key);
        if ($path === null) {
            return;
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($path, serialize($value));
    }

    private function pathFor(string $key): ?string
    {
        $dir = $this->cacheDir ?? dirname(__DIR__, 2) . '/Dataset/.cache';
        if ($dir === '') {
            return null;
        }
        return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . sha1($key) . '.cache';
    }
}
