<?php

declare(strict_types=1);

namespace ML\IDEA\Dataset\Loaders;

use ML\IDEA\Exceptions\InvalidArgumentException;

final class JsonDatasetLoader
{
    /** @return array<mixed> */
    public function load(string $path): array
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException("Dataset file not found: {$path}");
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new InvalidArgumentException("Unable to read dataset file: {$path}");
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException("Invalid JSON dataset: {$path}");
        }

        return $decoded;
    }
}
