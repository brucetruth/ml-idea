<?php

declare(strict_types=1);

namespace ML\IDEA\Math;

use ML\IDEA\Data\SparseVector;
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
        if (SparseVector::isSparseRow($a) || SparseVector::isSparseRow($b)) {
            return self::euclideanSparse($a, $b);
        }

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

    /**
     * Euclidean distance when either vector may be sparse (index => value).
     *
     * @param array<int, float|int> $a
     * @param array<int, float|int> $b
     */
    public static function euclideanSparse(array $a, array $b, ?int $dimensions = null): float
    {
        $aSparse = SparseVector::isSparseRow($a, $dimensions);
        $bSparse = SparseVector::isSparseRow($b, $dimensions);

        if (!$aSparse && !$bSparse) {
            return self::euclidean($a, $b);
        }

        $dims = $dimensions ?? max(
            $aSparse ? (empty($a) ? 0 : max(array_keys($a)) + 1) : count($a),
            $bSparse ? (empty($b) ? 0 : max(array_keys($b)) + 1) : count($b),
        );

        $sum = 0.0;
        for ($i = 0; $i < $dims; $i++) {
            $va = (float) ($aSparse ? ($a[$i] ?? 0.0) : ($a[$i] ?? 0.0));
            $vb = (float) ($bSparse ? ($b[$i] ?? 0.0) : ($b[$i] ?? 0.0));
            $delta = $va - $vb;
            $sum += $delta * $delta;
        }

        return sqrt($sum);
    }
}
