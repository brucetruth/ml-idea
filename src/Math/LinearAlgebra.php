<?php

declare(strict_types=1);

namespace ML\IDEA\Math;

use ML\IDEA\Exceptions\InvalidArgumentException;

final class LinearAlgebra
{
    /**
     * @param array<int, float|int> $a
     * @param array<int, float|int> $b
     */
    public static function dot(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            throw new InvalidArgumentException('Vectors must have the same size for dot product.');
        }

        $sum = 0.0;
        foreach ($a as $i => $value) {
            $sum += (float) $value * (float) $b[$i];
        }

        return $sum;
    }

    public static function sigmoid(float $x): float
    {
        if ($x >= 0) {
            $z = exp(-$x);
            return 1.0 / (1.0 + $z);
        }

        $z = exp($x);
        return $z / (1.0 + $z);
    }
}
