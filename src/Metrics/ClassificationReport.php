<?php

declare(strict_types=1);

namespace ML\IDEA\Metrics;

final class ClassificationReport
{
    /**
     * @param array<int, int|float|string|bool> $truth
     * @param array<int, int|float|string|bool> $predictions
     * @return array<string, array{precision: float, recall: float, f1: float, support: int}>
     */
    public static function generate(array $truth, array $predictions): array
    {
        $labels = array_values(array_unique(array_merge($truth, $predictions), SORT_REGULAR));
        $report = [];

        foreach ($labels as $label) {
            $precision = ClassificationMetrics::precision($truth, $predictions, $label);
            $recall = ClassificationMetrics::recall($truth, $predictions, $label);
            $f1 = ClassificationMetrics::f1Score($truth, $predictions, $label);
            $support = 0;

            foreach ($truth as $value) {
                if ($value === $label) {
                    $support++;
                }
            }

            $report[self::labelKey($label)] = [
                'precision' => $precision,
                'recall' => $recall,
                'f1' => $f1,
                'support' => $support,
            ];
        }

        return $report;
    }

    /**
     * @param array<int, int|float|string|bool> $truth
     * @param array<int, int|float|string|bool> $predictions
     * @return array<string, array<string, int>>
     */
    public static function confusionMatrix(array $truth, array $predictions): array
    {
        $labels = array_values(array_unique(array_merge($truth, $predictions), SORT_REGULAR));
        $keys = array_map([self::class, 'labelKey'], $labels);

        $matrix = [];
        foreach ($keys as $actualKey) {
            $matrix[$actualKey] = [];
            foreach ($keys as $predKey) {
                $matrix[$actualKey][$predKey] = 0;
            }
        }

        foreach ($truth as $i => $actual) {
            $actualKey = self::labelKey($actual);
            $predKey = self::labelKey($predictions[$i]);
            $matrix[$actualKey][$predKey]++;
        }

        return $matrix;
    }

    private static function labelKey(int|float|string|bool $label): string
    {
        return get_debug_type($label) . ':' . json_encode($label, JSON_THROW_ON_ERROR);
    }
}
