<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Lexicon;

use ML\IDEA\Dataset\Loaders\JsonDatasetLoader;

final class WordNetLexicon
{
    /** @var array<string, array<int, string>> */
    private array $words;
    /** @var array<string, array<string, mixed>> */
    private array $synsets;

    public function __construct(?string $datasetPath = null)
    {
        $path = $datasetPath ?? dirname(__DIR__, 2) . '/Dataset/wordnet/wn.json';
        $data = (new JsonDatasetLoader())->load($path);
        $this->words = is_array($data['words'] ?? null) ? $data['words'] : [];
        $this->synsets = is_array($data['synsets'] ?? null) ? $data['synsets'] : [];
    }

    /** @return array<int, string> */
    public function synonyms(string $word, int $max = 20): array
    {
        $key = mb_strtolower(trim($word));
        $ids = $this->words[$key] ?? [];
        $out = [];

        foreach ($ids as $id) {
            $s = $this->synsets[$id] ?? null;
            if (!is_array($s)) {
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
        $ids = $this->words[$key] ?? [];
        foreach ($ids as $id) {
            $s = $this->synsets[$id] ?? null;
            if (is_array($s) && isset($s['definition'])) {
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
}
