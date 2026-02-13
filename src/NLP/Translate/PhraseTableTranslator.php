<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Translate;

use ML\IDEA\NLP\Contracts\TranslatorInterface;
use ML\IDEA\NLP\Normalize\UnicodeNormalizer;

final class PhraseTableTranslator implements TranslatorInterface
{
    /** @var array<string, string> */
    private array $phraseTable;

    /** @param array<string, string|array<int, string>> $phraseTable */
    public function __construct(
        array $phraseTable,
        private readonly int $minGram = 2,
        private readonly int $maxGram = 5,
    ) {
        $map = [];
        foreach ($phraseTable as $from => $to) {
            $norm = $this->normalize($from);
            if ($norm === '' || !str_contains($norm, ' ')) {
                continue;
            }

            if (is_array($to)) {
                $to = (string) ($to[0] ?? '');
            }
            $to = trim((string) $to);
            if ($to === '') {
                continue;
            }

            $map[$norm] = $to;
        }

        $this->phraseTable = $map;
    }

    public function translate(string $text, ?string $sourceLang = null, ?string $targetLang = null): string
    {
        $parts = preg_split('/(\P{L}+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];

        $wordPartIndexes = [];
        $words = [];
        foreach ($parts as $partIndex => $part) {
            if ($part !== '' && preg_match('/^\p{L}+$/u', $part) === 1) {
                $wordPartIndexes[] = $partIndex;
                $words[] = $part;
            }
        }

        $i = 0;
        $wordCount = count($words);
        $minN = max(2, $this->minGram);
        $maxN = max($minN, $this->maxGram);

        while ($i < $wordCount) {
            $matched = false;
            $maxTry = min($maxN, $wordCount - $i);

            for ($n = $maxTry; $n >= $minN; $n--) {
                $slice = array_slice($words, $i, $n);
                $key = $this->normalize(implode(' ', $slice));
                $translated = $this->phraseTable[$key] ?? null;
                if ($translated === null) {
                    continue;
                }

                $firstPartIndex = $wordPartIndexes[$i];
                $lastPartIndex = $wordPartIndexes[$i + $n - 1];
                $parts[$firstPartIndex] = $this->applyCasePattern($slice[0], $translated);
                for ($p = $firstPartIndex + 1; $p <= $lastPartIndex; $p++) {
                    $parts[$p] = '';
                }

                $i += $n;
                $matched = true;
                break;
            }

            if (!$matched) {
                $i++;
            }
        }

        return implode('', $parts);
    }

    private function normalize(string $text): string
    {
        $t = mb_strtolower(UnicodeNormalizer::stripAccents($text));
        return trim((string) preg_replace('/\s+/u', ' ', $t));
    }

    private function applyCasePattern(string $sourceFirstWord, string $target): string
    {
        if ($sourceFirstWord === '' || $target === '') {
            return $target;
        }

        if (mb_strtoupper($sourceFirstWord) === $sourceFirstWord) {
            return mb_strtoupper($target);
        }

        $first = mb_substr($sourceFirstWord, 0, 1);
        if ($first !== '' && mb_strtoupper($first) === $first) {
            return mb_strtoupper(mb_substr($target, 0, 1)) . mb_substr($target, 1);
        }

        return $target;
    }
}
