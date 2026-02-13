<?php

declare(strict_types=1);

namespace ML\IDEA\ModelSelection;

use ML\IDEA\Contracts\ClassifierInterface;
use ML\IDEA\Contracts\ProbabilisticClassifierInterface;
use ML\IDEA\Contracts\RegressorInterface;
use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Metrics\ClassificationMetrics;
use ML\IDEA\Metrics\RegressionMetrics;

final class CrossValidation
{
    /**
     * @param array<int, array<int, float|int>> $samples
     * @param array<int, int|float|string|bool> $labels
     * @param array<int, array{train: array<int, int>, test: array<int, int>}> $folds
     * @return array<int, float>
     */
    public static function crossValScoreClassifier(
        ClassifierInterface $estimator,
        array $samples,
        array $labels,
        array $folds,
        string $scoring = 'accuracy',
    ): array {
        $scores = [];

        foreach ($folds as $fold) {
            [$xTrain, $yTrain, $xTest, $yTest] = self::sliceByFold($samples, $labels, $fold['train'], $fold['test']);

            $model = $estimator->cloneWithParams([]);
            $model->fit($xTrain, $yTrain);

            if (in_array($scoring, ['roc_auc', 'pr_auc', 'log_loss', 'brier'], true)) {
                if (!$model instanceof ProbabilisticClassifierInterface) {
                    throw new InvalidArgumentException(sprintf('Scoring %s requires ProbabilisticClassifierInterface.', $scoring));
                }

                $positiveClass = $yTrain[0];
                $scoresVec = [];
                foreach ($xTest as $sample) {
                    $proba = $model->predictProba($sample);
                    $scoresVec[] = isset($proba[$positiveClass])
                        ? (float) $proba[$positiveClass]
                        : (float) array_values($proba)[0];
                }

                if ($scoring === 'roc_auc') {
                    $scores[] = ClassificationMetrics::rocAuc($yTest, $scoresVec, $positiveClass);
                } elseif ($scoring === 'pr_auc') {
                    $scores[] = ClassificationMetrics::prAuc($yTest, $scoresVec, $positiveClass);
                } elseif ($scoring === 'log_loss') {
                    $scores[] = -ClassificationMetrics::logLoss($yTest, $scoresVec, $positiveClass);
                } else {
                    $scores[] = -ClassificationMetrics::brierScore($yTest, $scoresVec, $positiveClass);
                }

                continue;
            }

            $predictions = $model->predictBatch($xTest);
            $scores[] = match ($scoring) {
                'accuracy' => ClassificationMetrics::accuracy($yTest, $predictions),
                'f1' => ClassificationMetrics::f1Score($yTest, $predictions, $yTrain[0]),
                'mcc' => ClassificationMetrics::matthewsCorrcoef($yTest, $predictions, $yTrain[0]),
                default => throw new InvalidArgumentException(sprintf('Unsupported classifier scoring: %s', $scoring)),
            };
        }

        return $scores;
    }

    /**
     * @param array<int, array<int, float|int>> $samples
     * @param array<int, int|float|string|bool> $labels
     * @param array<int, array{train: array<int, int>, test: array<int, int>}> $folds
     * @return array<int, int|float|string|bool>
     */
    public static function crossValPredictClassifier(
        ClassifierInterface $estimator,
        array $samples,
        array $labels,
        array $folds,
    ): array {
        $predictions = array_fill(0, count($samples), null);

        foreach ($folds as $fold) {
            [$xTrain, $yTrain, $xTest] = self::sliceByFold($samples, $labels, $fold['train'], $fold['test']);

            $model = $estimator->cloneWithParams([]);
            $model->fit($xTrain, $yTrain);
            $foldPredictions = $model->predictBatch($xTest);

            foreach ($fold['test'] as $i => $index) {
                $predictions[$index] = $foldPredictions[$i];
            }
        }

        /** @var array<int, int|float|string|bool> $predictions */
        return $predictions;
    }

    /**
     * @param array<int, array<int, float|int>> $samples
     * @param array<int, float|int> $targets
     * @param array<int, array{train: array<int, int>, test: array<int, int>}> $folds
     * @return array<int, float>
     */
    public static function crossValScoreRegressor(
        RegressorInterface $estimator,
        array $samples,
        array $targets,
        array $folds,
        string $scoring = 'r2',
    ): array {
        $scores = [];

        foreach ($folds as $fold) {
            [$xTrain, $yTrain, $xTest, $yTest] = self::sliceByFold($samples, $targets, $fold['train'], $fold['test']);

            $model = $estimator->cloneWithParams([]);
            $model->fit($xTrain, $yTrain);
            $predictions = $model->predictBatch($xTest);

            $scores[] = match ($scoring) {
                'r2' => RegressionMetrics::r2Score($yTest, $predictions),
                'rmse' => -RegressionMetrics::rootMeanSquaredError($yTest, $predictions),
                'mae' => -RegressionMetrics::meanAbsoluteError($yTest, $predictions),
                'mape' => -RegressionMetrics::meanAbsolutePercentageError($yTest, $predictions),
                default => throw new InvalidArgumentException(sprintf('Unsupported regressor scoring: %s', $scoring)),
            };
        }

        return $scores;
    }

    /**
     * @param array<int, array<int, float|int>> $samples
     * @param array<int, float|int> $targets
     * @param array<int, array{train: array<int, int>, test: array<int, int>}> $folds
     * @return array<int, float>
     */
    public static function crossValPredictRegressor(
        RegressorInterface $estimator,
        array $samples,
        array $targets,
        array $folds,
    ): array {
        $predictions = array_fill(0, count($samples), null);

        foreach ($folds as $fold) {
            [$xTrain, $yTrain, $xTest] = self::sliceByFold($samples, $targets, $fold['train'], $fold['test']);

            $model = $estimator->cloneWithParams([]);
            $model->fit($xTrain, $yTrain);
            $foldPredictions = $model->predictBatch($xTest);

            foreach ($fold['test'] as $i => $index) {
                $predictions[$index] = (float) $foldPredictions[$i];
            }
        }

        /** @var array<int, float> $predictions */
        return $predictions;
    }

    /**
     * @template T
     * @param array<int, array<int, float|int>> $samples
     * @param array<int, T> $targets
     * @param array<int, int> $trainIdx
     * @param array<int, int> $testIdx
     * @return array{0: array<int, array<int, float|int>>, 1: array<int, T>, 2: array<int, array<int, float|int>>, 3: array<int, T>}
     */
    private static function sliceByFold(array $samples, array $targets, array $trainIdx, array $testIdx): array
    {
        $xTrain = $yTrain = $xTest = $yTest = [];

        foreach ($trainIdx as $i) {
            $xTrain[] = $samples[$i];
            $yTrain[] = $targets[$i];
        }
        foreach ($testIdx as $i) {
            $xTest[] = $samples[$i];
            $yTest[] = $targets[$i];
        }

        return [$xTrain, $yTrain, $xTest, $yTest];
    }
}
