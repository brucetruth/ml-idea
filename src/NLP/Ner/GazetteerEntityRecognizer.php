<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Ner;

use ML\IDEA\Dataset\Index\AhoCorasickAutomaton;
use ML\IDEA\NLP\Normalize\UnicodeNormalizer;

final class GazetteerEntityRecognizer
{
    private AhoCorasickAutomaton $automaton;

    /** @param array<string, string> $gazetteer */
    public function __construct(
        array $gazetteer,
        private readonly bool $caseInsensitive = true,
        private readonly bool $normalizeAccents = true,
        private readonly bool $collapseWhitespace = true,
    ) {
        $patterns = [];
        foreach ($gazetteer as $term => $label) {
            $patterns[$this->normalize($term)] = (string) $label;
        }
        $this->automaton = AhoCorasickAutomaton::fromMap($patterns);
    }

    /** @return array<int, Entity> */
    public function recognize(string $text): array
    {
        $norm = $this->normalize($text);
        $matches = $this->automaton->find($norm);

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
