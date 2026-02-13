<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Privacy;

final class SensitiveTermFilter
{
    /** @param array<int, string> $terms */
    public function __construct(private readonly array $terms, private readonly bool $fuzzy = false, private readonly int $maxDistance = 1)
    {
    }

    /** @return array<int, string> */
    public function find(string $text): array
    {
        $found = [];
        $lower = mb_strtolower($text);

        foreach ($this->terms as $term) {
            $needle = mb_strtolower($term);
            if (str_contains($lower, $needle)) {
                $found[] = $term;
                continue;
            }

            if ($this->fuzzy) {
                foreach (preg_split('/\W+/u', $lower) ?: [] as $word) {
                    if ($word === '') {
                        continue;
                    }
                    if (levenshtein($needle, $word) <= $this->maxDistance) {
                        $found[] = $term;
                        break;
                    }
                }
            }
        }

        return array_values(array_unique($found));
    }

    public function redact(string $text, string $mask = '[SENSITIVE]'): string
    {
        $out = $text;
        foreach ($this->find($text) as $term) {
            $out = (string) preg_replace('/\b' . preg_quote($term, '/') . '\b/iu', $mask, $out);
        }
        return $out;
    }
}
