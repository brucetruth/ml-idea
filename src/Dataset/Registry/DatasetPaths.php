<?php

declare(strict_types=1);

namespace ML\IDEA\Dataset\Registry;

final class DatasetPaths
{
    /**
     * Dataset root. Prefers {@see src/Dataset} (full bundled data), then {@see src/datasets} (minimal seeds).
     */
    public static function base(?string $override = null): string
    {
        if ($override !== null && $override !== '') {
            return rtrim($override, '/');
        }

        $root = dirname(__DIR__, 2);
        foreach (['Dataset', 'datasets'] as $name) {
            $path = $root . '/' . $name;
            if (is_dir($path)) {
                return $path;
            }
        }

        return $root . '/Dataset';
    }

    /** @return array<int, string> */
    public static function candidates(string $relative, ?string $override = null): array
    {
        if ($override !== null && $override !== '') {
            return [rtrim($override, '/') . '/' . ltrim($relative, '/')];
        }

        $root = dirname(__DIR__, 2);
        $relative = ltrim($relative, '/');

        return [
            $root . '/Dataset/' . $relative,
            $root . '/datasets/' . $relative,
        ];
    }

    public static function resolve(string $relative, ?string $override = null): string
    {
        $matches = [];
        foreach (self::candidates($relative, $override) as $path) {
            if (is_file($path) || is_dir($path)) {
                $matches[] = $path;
            }
        }

        if ($matches === []) {
            return self::base($override) . '/' . ltrim($relative, '/');
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        // When both trees ship a file, prefer the larger one (full export over seed).
        usort($matches, static function (string $a, string $b): int {
            $sizeA = is_file($a) ? (int) filesize($a) : self::directoryWeight($a);
            $sizeB = is_file($b) ? (int) filesize($b) : self::directoryWeight($b);

            return $sizeB <=> $sizeA;
        });

        return $matches[0];
    }

    /** Smallest matching file under src/datasets (seed tree), when present. */
    public static function seed(string $relative, ?string $override = null): string
    {
        if ($override !== null && $override !== '') {
            return rtrim($override, '/') . '/' . ltrim($relative, '/');
        }

        $root = dirname(__DIR__, 2);
        $seed = $root . '/datasets/' . ltrim($relative, '/');
        if (is_file($seed) || is_dir($seed)) {
            return $seed;
        }

        return self::resolve($relative, $override);
    }

    private static function directoryWeight(string $path): int
    {
        if (!is_dir($path)) {
            return 0;
        }

        $weight = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $weight += (int) $file->getSize();
            }
        }

        return $weight;
    }
}
