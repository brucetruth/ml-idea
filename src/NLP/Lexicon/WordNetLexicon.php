<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Lexicon;

use ML\IDEA\Dataset\Loaders\JsonDatasetLoader;
use ML\IDEA\Dataset\Loaders\JsonFileKeyScanner;
use ML\IDEA\Dataset\Registry\DatasetPaths;

final class WordNetLexicon
{
    private const STREAMING_THRESHOLD_BYTES = 1_048_576;

    private readonly string $datasetPath;

    private bool $streaming = false;

    /** @var array<string, array<int, string>>|null */
    private ?array $words = null;

    /** @var array<string, array<string, mixed>>|null */
    private ?array $synsets = null;

    /** @var array<string, array<int, string>> */
    private array $wordCache = [];

    /** @var array<string, array<string, mixed>|null> */
    private array $synsetCache = [];

    private ?JsonFileKeyScanner $scanner = null;

    public function __construct(?string $datasetPath = null)
    {
        $this->datasetPath = $datasetPath ?? DatasetPaths::resolve('wordnet/wn.json');
    }

    /** @return array<int, string> */
    public function synonyms(string $word, int $max = 20, bool $primarySenseOnly = false): array
    {
        $key = mb_strtolower(trim($word));
        $ids = $this->synsetIdsForWord($key);
        if ($primarySenseOnly && $ids !== []) {
            $ids = [reset($ids)];
        }
        $out = [];

        foreach ($ids as $id) {
            $s = $this->synset($id);
            if ($s === null) {
                continue;
            }
            foreach (($s['synonyms'] ?? []) as $syn) {
                $syn = mb_strtolower((string) $syn);
                if ($syn !== '' && !in_array($syn, $out, true)) {
                    $out[] = $syn;
                    if (count($out) >= $max) {
                        return $out;
                    }
                }
            }
        }

        return $out;
    }

    public function definition(string $word): ?string
    {
        $key = mb_strtolower(trim($word));
        foreach ($this->synsetIdsForWord($key) as $id) {
            $s = $this->synset($id);
            if ($s !== null && isset($s['definition'])) {
                return (string) $s['definition'];
            }
        }

        return null;
    }

    /** @param array<int, string> $terms @return array<int, string> */
    public function expandTerms(array $terms, int $synonymsPerTerm = 5): array
    {
        $expanded = [];
        foreach ($terms as $term) {
            $term = mb_strtolower(trim($term));
            if ($term === '') {
                continue;
            }
            if (!in_array($term, $expanded, true)) {
                $expanded[] = $term;
            }
            foreach ($this->synonyms($term, $synonymsPerTerm) as $syn) {
                if (!in_array($syn, $expanded, true)) {
                    $expanded[] = $syn;
                }
            }
        }

        return $expanded;
    }

    /** Warm the lexicon cache (loads small files fully; large files stay stream-backed). */
    public function preload(): void
    {
        $this->bootstrapStorage();
    }

    private function bootstrapStorage(): void
    {
        if ($this->words !== null || $this->streaming) {
            return;
        }

        $size = is_file($this->datasetPath) ? (int) filesize($this->datasetPath) : 0;
        if ($size > self::STREAMING_THRESHOLD_BYTES) {
            $this->streaming = true;
            $this->scanner = new JsonFileKeyScanner($this->datasetPath);

            return;
        }

        $data = (new JsonDatasetLoader())->load($this->datasetPath);
        $this->words = is_array($data['words'] ?? null) ? $data['words'] : [];
        $this->synsets = is_array($data['synsets'] ?? null) ? $data['synsets'] : [];
    }

    /** @return array<int, string> */
    private function synsetIdsForWord(string $key): array
    {
        if ($key === '') {
            return [];
        }

        if (isset($this->wordCache[$key])) {
            return $this->wordCache[$key];
        }

        $this->bootstrapStorage();

        if ($this->streaming) {
            $ids = $this->scanner?->readStringArray($key) ?? [];
            $this->wordCache[$key] = $ids;

            return $ids;
        }

        $ids = $this->words[$key] ?? [];
        $this->wordCache[$key] = $ids;

        return $ids;
    }

    /** @return array<string, mixed>|null */
    private function synset(string $id): ?array
    {
        if ($id === '') {
            return null;
        }

        if (array_key_exists($id, $this->synsetCache)) {
            $cached = $this->synsetCache[$id];

            return is_array($cached) ? $cached : null;
        }

        $this->bootstrapStorage();

        if ($this->streaming) {
            $synset = $this->scanner?->readObject($id);
            if (!is_array($synset)) {
                $this->synsetCache[$id] = null;

                return null;
            }
            $this->synsetCache[$id] = $synset;

            return $synset;
        }

        $synset = $this->synsets[$id] ?? null;
        if (!is_array($synset)) {
            $this->synsetCache[$id] = null;

            return null;
        }

        $this->synsetCache[$id] = $synset;

        return $synset;
    }
}
