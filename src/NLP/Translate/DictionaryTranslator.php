<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Translate;

use ML\IDEA\NLP\Contracts\TranslatorInterface;
use ML\IDEA\NLP\Normalize\UnicodeNormalizer;

final class DictionaryTranslator implements TranslatorInterface
{
    /** @var array<string, string> */
    private array $dictionary;

    /** @param array<string, string|array<int, string>> $dictionary */
    public function __construct(array $dictionary)
    {
        $out = [];
        foreach ($dictionary as $from => $to) {
            $norm = $this->normalize($from);
            if ($norm === '') {
                continue;
            }

            if (is_array($to)) {
                $to = (string) ($to[0] ?? '');
            }

            $to = trim((string) $to);
            if ($to === '') {
                continue;
            }

            $out[$norm] = $to;
        }

        $this->dictionary = $out;
    }

    public function translateWord(string $word): string
    {
        $translated = $this->dictionary[$this->normalize($word)] ?? $word;
        return $this->applyCasePattern($word, $translated);
    }

    public function translate(string $text, ?string $sourceLang = null, ?string $targetLang = null): string
    {
        $parts = preg_split('/(\P{L}+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        $out = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (preg_match('/^\p{L}+$/u', $part) === 1) {
                $out[] = $this->translateWord($part);
                continue;
            }

            $out[] = $part;
        }

        return implode('', $out);
    }

    private function normalize(string $text): string
    {
        $t = mb_strtolower(UnicodeNormalizer::stripAccents($text));
        return trim((string) preg_replace('/\s+/u', ' ', $t));
    }

    private function applyCasePattern(string $source, string $target): string
    {
        if ($source === '' || $target === '') {
            return $target;
        }

        if (mb_strtoupper($source) === $source) {
            return mb_strtoupper($target);
        }

        $first = mb_substr($source, 0, 1);
        if ($first !== '' && mb_strtoupper($first) === $first) {
            return mb_strtoupper(mb_substr($target, 0, 1)) . mb_substr($target, 1);
        }

        return $target;
    }
}
