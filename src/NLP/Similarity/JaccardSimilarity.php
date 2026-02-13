<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Similarity;

final class JaccardSimilarity
{
    /** @param array<int, string> $a @param array<int, string> $b */
    public static function between(array $a, array $b): float
    {
        $sa = array_fill_keys($a, true);
        $sb = array_fill_keys($b, true);
        $inter = count(array_intersect_key($sa, $sb));
        $union = count($sa) + count($sb) - $inter;

        return $union === 0 ? 0.0 : $inter / $union;
    }
}
