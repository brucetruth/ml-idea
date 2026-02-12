<?php

declare(strict_types=1);

namespace ML\IDEA\Math;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Support\Assert;

final class Distance
{
    /**
     * @param array<int, float|int> $a
     * @param array<int, float|int> $b
     */
    public static function euclidean(array $a, array $b): float
    {
        Assert::numericVector($a);
        Assert::numericVector($b);

        if (count($a) !== count($b)) {
            throw new InvalidArgumentException('Vectors must have the same dimension.');
        }

        $sum = 0.0;
        foreach ($a as $index => $value) {
            $delta = (float) $value - (float) $b[$index];
            $sum += $delta * $delta;
        }

        return sqrt($sum);
    }
}
