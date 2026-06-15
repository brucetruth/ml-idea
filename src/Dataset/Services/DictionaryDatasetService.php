<?php

declare(strict_types=1);

namespace ML\IDEA\Dataset\Services;

use ML\IDEA\Dataset\Loaders\CsvDatasetLoader;
use ML\IDEA\Dataset\Registry\DatasetPaths;

final class DictionaryDatasetService
{
    /** @var array<string, array<int, string>>|null */
    private ?array $englishToBemba = null;
    /** @var array<string, string>|null */
    private ?array $englishDefinitions = null;

    public function __construct(private readonly ?string $basePath = null)
    {
    }

    /** @return array<string, array<int, string>> */
    public function englishToBemba(): array
    {
        if ($this->englishToBemba !== null) {
            return $this->englishToBemba;
        }

        $path = $this->basePath ?? DatasetPaths::resolve('dictionary');
        $rows = (new CsvDatasetLoader())->loadAssoc($path . '/bemba/english_to_bemba.csv');

        $map = [];
        foreach ($rows as $row) {
            $en = mb_strtolower(trim((string) ($row['English'] ?? '')));
            $b1 = trim((string) ($row['Bemba'] ?? ''));
            $b2 = trim((string) ($row['Bemba2'] ?? ''));

            if ($en === '') {
                continue;
            }

            $vals = array_values(array_filter([$b1, $b2], static fn (string $v): bool => $v !== ''));
            if ($vals === []) {
                continue;
            }

            $existing = $map[$en] ?? [];
            $map[$en] = array_values(array_unique(array_merge($existing, $vals)));
        }

        $this->englishToBemba = $map;
        return $map;
    }

    /** @return array<string, string> */
    public function englishToBembaWordMap(): array
    {
        $out = [];
        foreach ($this->englishToBemba() as $en => $translations) {
            if (str_contains($en, ' ')) {
                continue;
            }
            $v = trim((string) ($translations[0] ?? ''));
            if ($v === '') {
                continue;
            }
            $out[$this->normalizeKey($en)] = $v;
        }

        return $out;
    }

    /** @return array<string, string> */
    public function englishToBembaPhraseMap(int $minN = 2, int $maxN = 5): array
    {
        $minN = max(2, $minN);
        $maxN = max($minN, $maxN);

        $out = [];
        foreach ($this->englishToBemba() as $en => $translations) {
            $norm = $this->normalizeKey($en);
            if ($norm === '' || !str_contains($norm, ' ')) {
                continue;
            }

            $n = count(explode(' ', $norm));
            if ($n < $minN || $n > $maxN) {
                continue;
            }

            $v = trim((string) ($translations[0] ?? ''));
            if ($v === '') {
                continue;
            }

            $out[$norm] = $v;
        }

        return $out;
    }

    /** @return array<string, string> */
    public function englishToBembaSupplementalPhrases(): array
    {
        $out = [];
        foreach ($this->supplementalPhrasePaths() as $path) {
            if (!is_file($path)) {
                continue;
            }

            $rows = (new CsvDatasetLoader())->loadAssoc($path);
            foreach ($rows as $row) {
                $en = $this->normalizeKey((string) ($row['English'] ?? $row['english'] ?? ''));
                $bem = trim((string) ($row['Bemba'] ?? $row['bemba'] ?? ''));
                if ($en === '' || $bem === '' || !str_contains($en, ' ')) {
                    continue;
                }
                $out[$en] = $bem;
            }
        }

        return $out;
    }

    /** @return array<string, string> */
    public function bembaToEnglishWordMap(): array
    {
        $out = [];
        foreach ($this->englishToBemba() as $en => $translations) {
            if (str_contains($en, ' ')) {
                continue;
            }

            foreach ($translations as $bem) {
                $key = $this->normalizeKey($bem);
                if ($key === '' || isset($out[$key])) {
                    continue;
                }
                $out[$key] = $this->normalizeKey($en);
            }
        }

        return $out;
    }

    /** @return array<string, string> */
    public function bembaToEnglishPhraseMap(int $minN = 2, int $maxN = 5): array
    {
        $minN = max(2, $minN);
        $maxN = max($minN, $maxN);
        $out = [];

        $sources = array_merge(
            $this->englishToBembaPhraseMap($minN, $maxN),
            $this->englishToBembaSupplementalPhrases(),
        );

        foreach ($sources as $en => $bem) {
            $enNorm = $this->normalizeKey($en);
            $bemNorm = $this->normalizeKey($bem);
            if ($enNorm === '' || $bemNorm === '' || !str_contains($bemNorm, ' ')) {
                continue;
            }

            $n = count(explode(' ', $bemNorm));
            if ($n < $minN || $n > $maxN) {
                continue;
            }

            $out[$bemNorm] = $enNorm;
        }

        return $out;
    }

    /** @return array<int, string> */
    private function supplementalPhrasePaths(): array
    {
        if ($this->basePath !== null) {
            return [$this->basePath . '/bemba/phrases.csv'];
        }

        $relative = 'dictionary/bemba/phrases.csv';

        return array_values(array_unique([
            DatasetPaths::resolve($relative),
            DatasetPaths::seed($relative),
        ]));
    }

    /** @return array<string, string> */
    public function englishDefinitions(): array
    {
        if ($this->englishDefinitions !== null) {
            return $this->englishDefinitions;
        }

        $path = $this->basePath ?? DatasetPaths::resolve('dictionary');
        $rows = (new CsvDatasetLoader())->loadAssoc($path . '/en/en.csv');

        $map = [];
        foreach ($rows as $row) {
            $word = mb_strtolower(trim((string) ($row['word'] ?? '')));
            $definition = trim((string) ($row['definition'] ?? ''));
            if ($word === '' || $definition === '') {
                continue;
            }
            $map[$word] = $definition;
        }

        $this->englishDefinitions = $map;
        return $map;
    }

    private function normalizeKey(string $key): string
    {
        $k = mb_strtolower(trim($key));
        return trim((string) preg_replace('/\s+/u', ' ', $k));
    }
}
