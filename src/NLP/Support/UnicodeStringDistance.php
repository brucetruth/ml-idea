<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Support;

final class UnicodeStringDistance
{
    public static function levenshtein(string $a, string $b): int
    {
        $aLen = mb_strlen($a);
        $bLen = mb_strlen($b);

        if ($aLen === 0) {
            return $bLen;
        }
        if ($bLen === 0) {
            return $aLen;
        }

        /** @var array<int, int> $prev */
        $prev = range(0, $bLen);
        /** @var array<int, int> $curr */
        $curr = [];

        for ($i = 1; $i <= $aLen; $i++) {
            $curr[0] = $i;
            $aChar = mb_substr($a, $i - 1, 1);

            for ($j = 1; $j <= $bLen; $j++) {
                $cost = $aChar === mb_substr($b, $j - 1, 1) ? 0 : 1;
                $curr[$j] = min(
                    $prev[$j] + 1,
                    $curr[$j - 1] + 1,
                    $prev[$j - 1] + $cost,
                );
            }

            $prev = $curr;
        }

        return $prev[$bLen];
    }

    public static function similarity(string $a, string $b): float
    {
        if ($a === '' && $b === '') {
            return 1.0;
        }

        $distance = self::levenshtein($a, $b);
        $maxLen = max(mb_strlen($a), mb_strlen($b));
        if ($maxLen === 0) {
            return 1.0;
        }

        return max(0.0, 1.0 - ($distance / $maxLen));
    }
}
