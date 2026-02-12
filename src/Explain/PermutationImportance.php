<?php

declare(strict_types=1);

namespace ML\IDEA\Explain;

use ML\IDEA\Contracts\ClassifierInterface;
use ML\IDEA\Metrics\ClassificationMetrics;

final class PermutationImportance
{
    /**
     * @param array<int, array<int, float|int>> $samples
     * @param array<int, int|float|string|bool> $labels
     * @return array<int, float>
     */
    public static function forClassifier(
        ClassifierInterface $model,
        array $samples,
        array $labels,
        int $nRepeats = 3,
        ?int $seed = 42,
    ): array {
        if ($seed !== null) {
            mt_srand($seed);
        }

        $baselinePredictions = $model->predictBatch($samples);
        $baseline = ClassificationMetrics::accuracy($labels, $baselinePredictions);

        $featureCount = count($samples[0]);
        $importances = array_fill(0, $featureCount, 0.0);

        for ($j = 0; $j < $featureCount; $j++) {
            $drop = 0.0;

            for ($r = 0; $r < $nRepeats; $r++) {
                $permuted = $samples;
                $column = array_column($permuted, $j);
                shuffle($column);

                foreach ($permuted as $i => $row) {
                    $permuted[$i][$j] = $column[$i];
                }

                $predictions = $model->predictBatch($permuted);
                $score = ClassificationMetrics::accuracy($labels, $predictions);
                $drop += ($baseline - $score);
            }

            $importances[$j] = $drop / $nRepeats;
        }

        return $importances;
    }
}
