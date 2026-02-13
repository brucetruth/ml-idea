<?php

declare(strict_types=1);

namespace ML\IDEA\Dataset\Loaders;

use ML\IDEA\Exceptions\InvalidArgumentException;

final class CsvDatasetLoader
{
    /**
     * @return array<int, array<string, string>>
     */
    public function loadAssoc(string $path): array
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException("Dataset file not found: {$path}");
        }

        $h = fopen($path, 'rb');
        if ($h === false) {
            throw new InvalidArgumentException("Unable to open dataset file: {$path}");
        }

        $header = fgetcsv($h, 0, ',', '"', '\\');
        if (!is_array($header)) {
            fclose($h);
            return [];
        }

        $header = array_map(static fn ($v): string => trim((string) $v), $header);
        $rows = [];

        while (($row = fgetcsv($h, 0, ',', '"', '\\')) !== false) {
            $assoc = [];
            foreach ($header as $i => $key) {
                $k = $key === '' ? '_idx' : $key;
                $assoc[$k] = (string) ($row[$i] ?? '');
            }
            if (count(array_filter($assoc, static fn (string $v): bool => trim($v) !== '')) === 0) {
                continue;
            }
            $rows[] = $assoc;
        }

        fclose($h);
        return $rows;
    }
}
