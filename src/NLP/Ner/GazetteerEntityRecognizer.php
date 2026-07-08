<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Ner;

use ML\IDEA\Dataset\Index\AhoCorasickAutomaton;
use ML\IDEA\NLP\Normalize\UnicodeNormalizer;

final class GazetteerEntityRecognizer
{
    private const AHO_CORASICK_MAX_PATTERNS = 1000;

    /** @var array<string, string> */
    private array $patterns = [];
    private ?AhoCorasickAutomaton $automaton = null;

    /** @param array<string, string> $gazetteer */
    public function __construct(
        array $gazetteer,
        private readonly bool $caseInsensitive = true,
        private readonly bool $normalizeAccents = true,
        private readonly bool $collapseWhitespace = true,
    ) {
        foreach ($gazetteer as $term => $label) {
            $normalized = $this->normalize($term);
            if ($normalized === '') {
                continue;
            }
            $this->patterns[$normalized] = (string) $label;
        }

        if (count($this->patterns) <= self::AHO_CORASICK_MAX_PATTERNS) {
            $this->automaton = AhoCorasickAutomaton::fromMap($this->patterns);
        }
    }

    /** @return array<int, Entity> */
    public function recognize(string $text): array
    {
        $norm = $this->normalize($text);
        $matches = $this->automaton !== null
            ? $this->automaton->find($norm)
            : $this->findWithScan($norm);

        $entities = [];
        foreach ($matches as $m) {
            if (!$this->isBoundaryMatch($norm, (int) $m['start'], (int) $m['end'])) {
                continue;
            }

            $start = $this->safeByteOffset($norm, $text, (int) $m['start']);
            $endExclusiveNorm = (int) $m['end'] + 1;
            $end = $this->safeByteOffset($norm, $text, $endExclusiveNorm);
            if ($end <= $start) {
                continue;
            }

            $raw = substr($text, $start, $end - $start);
            $entities[] = new Entity(
                text: $raw,
                label: (string) $m['label'],
                start: $start,
                end: $end,
                confidence: 0.9,
            );
        }

        return $entities;
    }

    /** @return array<int, array{term:string,label:string,start:int,end:int}> */
    private function findWithScan(string $normalizedText): array
    {
        if ($this->patterns === [] || $normalizedText === '') {
            return [];
        }

        $terms = array_keys($this->patterns);
        usort($terms, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        $matches = [];
        foreach ($terms as $term) {
            $offset = 0;
            $termLength = mb_strlen($term);
            while (($pos = mb_strpos($normalizedText, $term, $offset)) !== false) {
                $end = $pos + $termLength - 1;
                if ($this->isBoundaryMatch($normalizedText, $pos, $end)) {
                    $matches[] = [
                        'term' => $term,
                        'label' => $this->patterns[$term],
                        'start' => $pos,
                        'end' => $end,
                    ];
                }
                $offset = $pos + 1;
            }
        }

        return $matches;
    }

    private function isBoundaryMatch(string $normalizedText, int $startChar, int $endCharInclusive): bool
    {
        $prev = $startChar > 0 ? mb_substr($normalizedText, $startChar - 1, 1) : '';
        $next = mb_substr($normalizedText, $endCharInclusive + 1, 1);

        if ($prev !== '' && preg_match('/[\p{L}\p{N}]/u', $prev) === 1) {
            return false;
        }
        if ($next !== '' && preg_match('/[\p{L}\p{N}]/u', $next) === 1) {
            return false;
        }

        return true;
    }

    private function normalize(string $text): string
    {
        $t = $text;
        if ($this->normalizeAccents) {
            $t = UnicodeNormalizer::stripAccents($t);
        }
        if ($this->caseInsensitive) {
            $t = mb_strtolower($t);
        }
        if ($this->collapseWhitespace) {
            $t = trim((string) preg_replace('/\s+/u', ' ', $t));
        }
        return $t;
    }

    private function safeByteOffset(string $normalized, string $original, int $normCharOffset): int
    {
        $prefix = mb_substr($normalized, 0, max(0, $normCharOffset));
        $needle = $this->normalize($prefix);
        if ($needle === '') {
            return 0;
        }

        $probe = '';
        $length = mb_strlen($original);
        for ($i = 0; $i < $length; $i++) {
            $probe .= mb_substr($original, $i, 1);
            if ($this->normalize($probe) === $needle) {
                return strlen($probe);
            }
        }

        return strlen($original);
    }
}
