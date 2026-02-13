<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Lexicon;

use ML\IDEA\Dataset\Loaders\CsvDatasetLoader;
use ML\IDEA\Dataset\Services\DictionaryDatasetService;

final class EnglishDictionaryLexicon
{
    /** @var array<string, string> */
    private array $definitions;

    public function __construct(?string $csvPath = null)
    {
        if ($csvPath !== null) {
            $rows = (new CsvDatasetLoader())->loadAssoc($csvPath);
            $map = [];
            foreach ($rows as $row) {
                $word = mb_strtolower(trim((string) ($row['word'] ?? '')));
                $definition = trim((string) ($row['definition'] ?? ''));
                if ($word !== '' && $definition !== '') {
                    $map[$word] = $definition;
                }
            }
            $this->definitions = $map;
            return;
        }

        $this->definitions = (new DictionaryDatasetService())->englishDefinitions();
    }

    public function definition(string $word): ?string
    {
        $key = mb_strtolower(trim($word));
        return $this->definitions[$key] ?? null;
    }

    /** @return array<int, string> */
    public function wordsFromMeaning(string $meaningQuery, int $limit = 25): array
    {
        $query = mb_strtolower(trim($meaningQuery));
        if ($query === '') {
            return [];
        }

        $terms = array_values(array_filter(
            preg_split('/\W+/u', $query) ?: [],
            static fn (string $t): bool => $t !== ''
        ));

        $scored = [];
        foreach ($this->definitions as $word => $definition) {
            $d = mb_strtolower($definition);
            $score = 0;
            foreach ($terms as $term) {
                if (mb_stripos($d, $term) !== false) {
                    $score++;
                }
            }
            if ($score > 0) {
                $scored[$word] = $score;
            }
        }

        arsort($scored);
        return array_slice(array_keys($scored), 0, $limit);
    }
}
