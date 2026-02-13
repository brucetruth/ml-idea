<?php

declare(strict_types=1);

namespace ML\IDEA\Metrics;

use ML\IDEA\Exceptions\InvalidArgumentException;

final class ClassificationMetrics
{
    /**
     * @param array<int, int|float|string|bool> $truth
     * @param array<int, float> $positiveScores
     */
    public static function rocAuc(array $truth, array $positiveScores, int|float|string|bool $positiveClass): float
    {
        self::assertComparable($truth, $positiveScores);

        $pairs = [];
        foreach ($truth as $i => $label) {
            $pairs[] = ['label' => $label, 'score' => (float) $positiveScores[$i]];
        }

        usort($pairs, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $pos = 0;
        $neg = 0;
        foreach ($truth as $label) {
            if ($label === $positiveClass) {
                $pos++;
            } else {
                $neg++;
            }
        }

        if ($pos === 0 || $neg === 0) {
            return 0.0;
        }

        $tp = 0.0;
        $fp = 0.0;
        $prevTpr = 0.0;
        $prevFpr = 0.0;
        $auc = 0.0;

        foreach ($pairs as $pair) {
            if ($pair['label'] === $positiveClass) {
                $tp++;
            } else {
                $fp++;
            }

            $tpr = $tp / $pos;
            $fpr = $fp / $neg;
            $auc += ($fpr - $prevFpr) * ($tpr + $prevTpr) / 2.0;
            $prevTpr = $tpr;
            $prevFpr = $fpr;
        }

        return $auc;
    }

    /**
     * @param array<int, int|float|string|bool> $truth
     * @param array<int, float> $positiveScores
     */
    public static function prAuc(array $truth, array $positiveScores, int|float|string|bool $positiveClass): float
    {
        self::assertComparable($truth, $positiveScores);

        $pairs = [];
        foreach ($truth as $i => $label) {
            $pairs[] = ['label' => $label, 'score' => (float) $positiveScores[$i]];
        }

        usort($pairs, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $tp = 0.0;
        $fp = 0.0;
        $pos = 0;
        foreach ($truth as $label) {
            if ($label === $positiveClass) {
                $pos++;
            }
        }

        if ($pos === 0) {
            return 0.0;
        }

        $prevRecall = 0.0;
        $prevPrecision = 1.0;
        $auc = 0.0;

        foreach ($pairs as $pair) {
            if ($pair['label'] === $positiveClass) {
                $tp++;
            } else {
                $fp++;
            }

            $recall = $tp / $pos;
            $precision = ($tp + $fp) === 0.0 ? 1.0 : $tp / ($tp + $fp);

            $auc += ($recall - $prevRecall) * ($precision + $prevPrecision) / 2.0;
            $prevRecall = $recall;
            $prevPrecision = $precision;
        }

        return $auc;
    }

    /**
     * @param array<int, int|float|string|bool> $truth
     * @param array<int, float> $positiveScores
     */
    public static function logLoss(array $truth, array $positiveScores, int|float|string|bool $positiveClass, float $epsilon = 1.0e-15): float
    {
        self::assertComparable($truth, $positiveScores);

        $loss = 0.0;
        foreach ($truth as $i => $label) {
            $y = $label === $positiveClass ? 1.0 : 0.0;
            $p = max($epsilon, min(1.0 - $epsilon, (float) $positiveScores[$i]));
            $loss += -($y * log($p) + ((1.0 - $y) * log(1.0 - $p)));
        }

        return $loss / count($truth);
    }

    /**
     * @param array<int, int|float|string|bool> $truth
     * @param array<int, float> $positiveScores
     */
    public static function brierScore(array $truth, array $positiveScores, int|float|string|bool $positiveClass): float
    {
        self::assertComparable($truth, $positiveScores);

        $sum = 0.0;
        foreach ($truth as $i => $label) {
            $y = $label === $positiveClass ? 1.0 : 0.0;
            $p = (float) $positiveScores[$i];
            $sum += ($p - $y) * ($p - $y);
        }

        return $sum / count($truth);
    }

    /**
     * @param array<int, int|float|string|bool> $truth
     * @param array<int, int|float|string|bool> $predictions
     */
    public static function matthewsCorrcoef(array $truth, array $predictions, int|float|string|bool $positiveClass): float
    {
        self::assertComparable($truth, $predictions);

        $tp = $tn = $fp = $fn = 0.0;
        foreach ($truth as $i => $label) {
            $pred = $predictions[$i];

            if ($label === $positiveClass && $pred === $positiveClass) {
                $tp++;
            } elseif ($label !== $positiveClass && $pred !== $positiveClass) {
                $tn++;
            } elseif ($label !== $positiveClass && $pred === $positiveClass) {
                $fp++;
            } else {
                $fn++;
            }
        }

        $den = sqrt(($tp + $fp) * ($tp + $fn) * ($tn + $fp) * ($tn + $fn));
        if ($den == 0.0) {
            return 0.0;
        }

        return (($tp * $tn) - ($fp * $fn)) / $den;
    }

    /**
     * @param array<int, int|float|string|bool> $truth
     * @param array<int, int|float|string|bool> $predictions
     */
    public static function accuracy(array $truth, array $predictions): float
    {
        self::assertComparable($truth, $predictions);

        $correct = 0;
        foreach ($truth as $i => $label) {
            if ($label === $predictions[$i]) {
                $correct++;
            }
        }

        return $correct / count($truth);
    }

    /**
     * @param array<int, int|float|string|bool> $truth
     * @param array<int, int|float|string|bool> $predictions
     * @param int|float|string|bool $positiveClass
     */
    public static function precision(array $truth, array $predictions, int|float|string|bool $positiveClass): float
    {
        self::assertComparable($truth, $predictions);

        $tp = 0;
        $fp = 0;

        foreach ($truth as $i => $label) {
            if ($predictions[$i] === $positiveClass) {
                if ($label === $positiveClass) {
                    $tp++;
                } else {
                    $fp++;
                }
            }
        }

        return $tp + $fp === 0 ? 0.0 : $tp / ($tp + $fp);
    }

    /**
     * @param array<int, int|float|string|bool> $truth
     * @param array<int, int|float|string|bool> $predictions
     * @param int|float|string|bool $positiveClass
     */
    public static function recall(array $truth, array $predictions, int|float|string|bool $positiveClass): float
    {
        self::assertComparable($truth, $predictions);

        $tp = 0;
        $fn = 0;

        foreach ($truth as $i => $label) {
            if ($label === $positiveClass) {
                if ($predictions[$i] === $positiveClass) {
                    $tp++;
                } else {
                    $fn++;
                }
            }
        }

        return $tp + $fn === 0 ? 0.0 : $tp / ($tp + $fn);
    }

    /**
     * @param array<int, int|float|string|bool> $truth
     * @param array<int, int|float|string|bool> $predictions
     * @param int|float|string|bool $positiveClass
     */
    public static function f1Score(array $truth, array $predictions, int|float|string|bool $positiveClass): float
    {
        $precision = self::precision($truth, $predictions, $positiveClass);
        $recall = self::recall($truth, $predictions, $positiveClass);

        return $precision + $recall === 0.0 ? 0.0 : (2 * $precision * $recall) / ($precision + $recall);
    }

    /**
     * @param array<int, int|float|string|bool> $truth
     * @param array<int, int|float|string|bool> $predictions
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
