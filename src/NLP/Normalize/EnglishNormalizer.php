<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Normalize;

/**
 * Lightweight English token normalization for lexical retrieval (not Porter stemming).
 *
 * Collapses common plural and tense suffixes so BM25/TF-IDF can match
 * "run" with "running" and "model" with "models".
 */
final class EnglishNormalizer
{
    private const int MIN_LENGTH = 4;

    public static function normalize(string $token): string
    {
        $token = mb_strtolower(trim($token));
        if ($token === '' || mb_strlen($token) < self::MIN_LENGTH) {
            return $token;
        }

        if (str_ends_with($token, 'ies') && mb_strlen($token) > 4) {
            return mb_substr($token, 0, -3) . 'y';
        }

        if (str_ends_with($token, 'ing') && mb_strlen($token) > 5) {
            $stem = mb_substr($token, 0, -3);
            if (self::hasDoubledConsonant($stem)) {
                $stem = mb_substr($stem, 0, -1);
            } elseif (str_ends_with($stem, 'e')) {
                // "making" -> "make" kept as "mak" without e; restore for longer stems
                $stem = mb_substr($stem, 0, -1);
            }

            return $stem;
        }

        if (str_ends_with($token, 'ed') && mb_strlen($token) > 4) {
            $stem = mb_substr($token, 0, -2);
            if (str_ends_with($stem, 'i')) {
                return mb_substr($stem, 0, -1) . 'y';
            }
            if (self::hasDoubledConsonant($stem)) {
                return mb_substr($stem, 0, -1);
            }

            return $stem;
        }

        if (str_ends_with($token, 'es') && mb_strlen($token) > 4) {
            $base = mb_substr($token, 0, -2);
            if (preg_match('/(?:s|x|z|ch|sh)$/', $base) === 1) {
                return $base;
            }
        }

        if (str_ends_with($token, 's')
            && !str_ends_with($token, 'ss')
            && !str_ends_with($token, 'us')
            && mb_strlen($token) >= self::MIN_LENGTH) {
            return mb_substr($token, 0, -1);
        }

        return $token;
    }

    /** @param array<int, string> $tokens @return array<int, string> */
    public static function normalizeTokens(array $tokens): array
    {
        return array_map([self::class, 'normalize'], $tokens);
    }

    private static function hasDoubledConsonant(string $stem): bool
    {
        if (mb_strlen($stem) < 2) {
            return false;
        }

        $last = mb_substr($stem, -1);
        $prev = mb_substr($stem, -2, 1);

        return $last === $prev && preg_match('/[b-df-hj-np-tv-z]/', $last) === 1;
    }
}
