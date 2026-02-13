<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Tokenize;

final class SentenceTokenizer
{
    /** @var array<int, string> */
    private array $abbreviations;

    /** @param array<int, string> $abbreviations */
    public function __construct(array $abbreviations = ['mr.', 'mrs.', 'ms.', 'dr.', 'prof.', 'inc.', 'ltd.', 'e.g.', 'i.e.', 'vs.'])
    {
        $this->abbreviations = array_map('mb_strtolower', $abbreviations);
    }

    /** @return array<int, string> */
    public function split(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $parts = preg_split('/(?<=[.!?])\s+/u', $text) ?: [$text];
        $sentences = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if ($sentences !== []) {
                $prev = mb_strtolower(trim((string) end($sentences)));
                foreach ($this->abbreviations as $abbr) {
                    if (str_ends_with($prev, $abbr)) {
                        $lastIndex = count($sentences) - 1;
                        $sentences[$lastIndex] .= ' ' . $part;
                        continue 2;
                    }
                }
            }

            $sentences[] = $part;
        }

        return $sentences;
    }
}
