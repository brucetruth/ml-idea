<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Similarity;

use ML\IDEA\NLP\Support\UnicodeStringDistance;

final class LevenshteinSimilarity
{
    public static function between(string $a, string $b): float
    {
        return UnicodeStringDistance::similarity($a, $b);
    }
}
