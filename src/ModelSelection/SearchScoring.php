<?php

declare(strict_types=1);

namespace ML\IDEA\ModelSelection;

use ML\IDEA\Contracts\ClassifierInterface;
use ML\IDEA\Contracts\ProbabilisticClassifierInterface;
use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Metrics\ClassificationMetrics;

final class SearchScoring
{
    /**
     * @param array<int, int|float|string|bool> $truth
     * @param array<int, int|float|string|bool> $predictions
     * @param array<int, array<int, float|int>> $xTest
     */
    public static function score(string $metric, ClassifierInterface $estimator, array $truth, array $predictions, array $xTest): float
    {
        return match ($metric) {
            'accuracy' => ClassificationMetrics::accuracy($truth, $predictions),
            'f1' => ClassificationMetrics::f1Score($truth, $predictions, $truth[0]),
            'roc_auc' => self::rocAuc($estimator, $truth, $xTest),
            'pr_auc' => self::prAuc($estimator, $truth, $xTest),
            default => throw new InvalidArgumentException(sprintf('Unsupported scoring metric: %s', $metric)),
        };
    }

    /**
     * @param array<int, array<int, float|int>> $samples
     * @param array<int, int|float|string|bool> $labels
     * @param array<int, int> $trainIdx
     * @param array<int, int> $testIdx
     * @return array{0: array<int, array<int, float|int>>, 1: array<int, int|float|string|bool>, 2: array<int, array<int, float|int>>, 3: array<int, int|float|string|bool>}
     */
    public static function buildFoldData(array $samples, array $labels, array $trainIdx, array $testIdx): array
    {
        $xTrain = $yTrain = $xTest = $yTest = [];

        foreach ($trainIdx as $i) {
            $xTrain[] = $samples[$i];
            $yTrain[] = $labels[$i];
        }
        foreach ($testIdx as $i) {
            $xTest[] = $samples[$i];
            $yTest[] = $labels[$i];
        }

        return [$xTrain, $yTrain, $xTest, $yTest];
    }

    /** @param array<int, int|float|string|bool> $truth @param array<int, array<int, float|int>> $xTest */
    private static function rocAuc(ClassifierInterface $estimator, array $truth, array $xTest): float
    {
        if (!$estimator instanceof ProbabilisticClassifierInterface) {
            throw new InvalidArgumentException('roc_auc scoring requires a probabilistic classifier.');
        }

        $classes = array_values(array_unique($truth, SORT_REGULAR));
        if (count($classes) !== 2) {
            throw new InvalidArgumentException('roc_auc scoring currently supports binary classification.');
        }

        $positive = $classes[1];
        $scores = array_map(
            static fn (array $sample): float => (float) (($estimator->predictProba($sample)[$positive] ?? 0.0)),
            $xTest,
        );

        return ClassificationMetrics::rocAuc($truth, $scores, $positive);
    }

    /** @param array<int, int|float|string|bool> $truth @param array<int, array<int, float|int>> $xTest */
    private static function prAuc(ClassifierInterface $estimator, array $truth, array $xTest): float
    {
        if (!$estimator instanceof ProbabilisticClassifierInterface) {
            throw new InvalidArgumentException('pr_auc scoring requires a probabilistic classifier.');
        }

        $classes = array_values(array_unique($truth, SORT_REGULAR));
        if (count($classes) !== 2) {
            throw new InvalidArgumentException('pr_auc scoring currently supports binary classification.');
        }

        $positive = $classes[1];
        $scores = array_map(
            static fn (array $sample): float => (float) (($estimator->predictProba($sample)[$positive] ?? 0.0)),
            $xTest,
        );

        return ClassificationMetrics::prAuc($truth, $scores, $positive);
    }
}
