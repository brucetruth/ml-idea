<?php

declare(strict_types=1);

namespace ML\IDEA\Metrics;

use ML\IDEA\Exceptions\InvalidArgumentException;

final class RegressionMetrics
{
    /**
     * @param array<int, float|int> $truth
     * @param array<int, float|int> $predictions
     */
    public static function meanSquaredError(array $truth, array $predictions): float
    {
        self::assertComparable($truth, $predictions);

        $sum = 0.0;
        foreach ($truth as $i => $actual) {
            $error = (float) $predictions[$i] - (float) $actual;
            $sum += $error * $error;
        }

        return $sum / count($truth);
    }

    /**
     * @param array<int, float|int> $truth
     * @param array<int, float|int> $predictions
     */
    public static function rootMeanSquaredError(array $truth, array $predictions): float
    {
        return sqrt(self::meanSquaredError($truth, $predictions));
    }

    /**
     * @param array<int, float|int> $truth
     * @param array<int, float|int> $predictions
     */
    public static function meanAbsoluteError(array $truth, array $predictions): float
    {
        self::assertComparable($truth, $predictions);

        $sum = 0.0;
        foreach ($truth as $i => $actual) {
            $sum += abs((float) $predictions[$i] - (float) $actual);
        }

        return $sum / count($truth);
    }

    /**
     * @param array<int, float|int> $truth
     * @param array<int, float|int> $predictions
     */
    public static function r2Score(array $truth, array $predictions): float
    {
        self::assertComparable($truth, $predictions);

        $mean = array_sum(array_map(static fn ($x): float => (float) $x, $truth)) / count($truth);
        $ssTot = 0.0;
        $ssRes = 0.0;

        foreach ($truth as $i => $actual) {
            $a = (float) $actual;
            $p = (float) $predictions[$i];
            $ssTot += ($a - $mean) * ($a - $mean);
            $ssRes += ($a - $p) * ($a - $p);
        }

        if ($ssTot == 0.0) {
            return 0.0;
        }

        return 1.0 - ($ssRes / $ssTot);
    }

    /**
     * @param array<int, float|int> $truth
     * @param array<int, float|int> $predictions
     */
    public static function meanAbsolutePercentageError(array $truth, array $predictions, float $epsilon = 1.0e-12): float
    {
        self::assertComparable($truth, $predictions);

        $sum = 0.0;
        foreach ($truth as $i => $actual) {
            $a = (float) $actual;
            $p = (float) $predictions[$i];
            $den = max(abs($a), $epsilon);
            $sum += abs(($a - $p) / $den);
        }

        return $sum / count($truth);
    }

    /**
     * @param array<int, float|int> $truth
     * @param array<int, float|int> $predictions
     */
    private static function assertComparable(array $truth, array $predictions): void
    {
        if ($truth === [] || $predictions === []) {
            throw new InvalidArgumentException('truth and predictions must be non-empty arrays.');
        }

        if (count($truth) !== count($predictions)) {
            throw new InvalidArgumentException('truth and predictions must have same length.');
        }
    }
}
