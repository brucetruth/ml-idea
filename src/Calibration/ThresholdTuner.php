<?php

declare(strict_types=1);

namespace ML\IDEA\Calibration;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Metrics\ClassificationMetrics;

final class ThresholdTuner
{
    /**
     * @param array<int, int|float|string|bool> $truth
     * @param array<int, float> $positiveScores
     */
    public static function optimize(
        array $truth,
        array $positiveScores,
        int|float|string|bool $positiveClass,
        string $metric = 'f1',
        int $steps = 101,
    ): float {
        if ($truth === [] || count($truth) !== count($positiveScores)) {
            throw new InvalidArgumentException('truth and positiveScores must be non-empty and same length.');
        }

        $negativeClass = null;
        foreach ($truth as $label) {
            if ($label !== $positiveClass) {
                $negativeClass = $label;
                break;
            }
        }
        if ($negativeClass === null) {
            $negativeClass = 0;
        }

        $bestThreshold = 0.5;
        $bestScore = -INF;

        for ($i = 0; $i < $steps; $i++) {
            $threshold = $i / max(1, $steps - 1);
            $pred = self::apply($positiveScores, $threshold, $positiveClass, $negativeClass);

            $score = match ($metric) {
                'precision' => ClassificationMetrics::precision($truth, $pred, $positiveClass),
                'recall' => ClassificationMetrics::recall($truth, $pred, $positiveClass),
                'accuracy' => ClassificationMetrics::accuracy($truth, $pred),
                default => ClassificationMetrics::f1Score($truth, $pred, $positiveClass),
            };

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestThreshold = $threshold;
            }
        }

        return $bestThreshold;
    }

    /**
     * @param array<int, float> $positiveScores
     * @return array<int, int|float|string|bool>
     */
    public static function apply(
        array $positiveScores,
        float $threshold,
        int|float|string|bool $positiveClass,
        int|float|string|bool $negativeClass,
    ): array {
        $predictions = [];
        foreach ($positiveScores as $score) {
            $predictions[] = $score >= $threshold ? $positiveClass : $negativeClass;
        }

        return $predictions;
    }
}
