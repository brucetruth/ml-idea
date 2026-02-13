<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Similarity;

final class LevenshteinSimilarity
{
    public static function between(string $a, string $b): float
    {
        if ($a === '' && $b === '') {
            return 1.0;
        }

        $d = levenshtein($a, $b);
        $m = max(strlen($a), strlen($b));
        if ($m === 0) {
            return 1.0;
        }

        return max(0.0, 1.0 - ($d / $m));
    }
}
