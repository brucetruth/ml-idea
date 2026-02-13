<?php

declare(strict_types=1);

namespace ML\IDEA\ModelSelection;

use ML\IDEA\Contracts\ClassifierInterface;
use ML\IDEA\Data\KFold;
use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Metrics\ClassificationMetrics;

final class GridSearchClassifier
{
    private readonly \Closure $factory;

    /** @var array<string, scalar|array<int, scalar>> */
    private array $bestParams = [];

    private float $bestScore = -INF;
    private ?ClassifierInterface $bestEstimator = null;

    /**
     * @param callable(array<string, scalar|array<int, scalar>>): ClassifierInterface $factory
     */
    public function __construct(
        callable $factory,
        private readonly string $scoring = 'accuracy',
        private readonly int $cv = 5,
    ) {
        $this->factory = \Closure::fromCallable($factory);

        if ($this->cv <= 1) {
            throw new InvalidArgumentException('cv must be greater than 1.');
        }
    }

    /**
     * @param array<int, array<int, float|int>> $samples
     * @param array<int, int|float|string|bool> $labels
     * @param array<string, array<int, scalar>> $paramGrid
     */
    public function fit(array $samples, array $labels, array $paramGrid): void
    {
        if ($samples === [] || $labels === [] || count($samples) !== count($labels)) {
            throw new InvalidArgumentException('Samples and labels must be non-empty and have same length.');
        }

        $paramCombinations = self::expandGrid($paramGrid);
        foreach ($paramCombinations as $params) {
            $scores = [];
            $folds = KFold::split(count($samples), $this->cv, true, 42);

            foreach ($folds as $fold) {
                [$xTrain, $yTrain, $xTest, $yTest] = self::buildFoldData($samples, $labels, $fold['train'], $fold['test']);
                $estimator = ($this->factory)($params);
                $estimator->train($xTrain, $yTrain);
                $predictions = $estimator->predictBatch($xTest);
                $scores[] = $this->score($yTest, $predictions);
            }

            $mean = array_sum($scores) / count($scores);
            if ($mean > $this->bestScore) {
                $this->bestScore = $mean;
                $this->bestParams = $params;
            }
        }

        $this->bestEstimator = ($this->factory)($this->bestParams);
        $this->bestEstimator->train($samples, $labels);
    }

    /** @return array<string, scalar|array<int, scalar>> */
    public function bestParams(): array
    {
        return $this->bestParams;
    }

    public function bestScore(): float
    {
        return $this->bestScore;
    }

    public function bestEstimator(): ClassifierInterface
    {
        if ($this->bestEstimator === null) {
            throw new InvalidArgumentException('Grid search has not been fitted yet.');
        }

        return $this->bestEstimator;
    }

    /**
     * @param array<int, int|float|string|bool> $truth
     * @param array<int, int|float|string|bool> $predictions
     */
    private function score(array $truth, array $predictions): float
    {
        if ($this->scoring === 'accuracy') {
            return ClassificationMetrics::accuracy($truth, $predictions);
        }

        if ($this->scoring === 'f1') {
            $positiveClass = $truth[0];
            return ClassificationMetrics::f1Score($truth, $predictions, $positiveClass);
        }

        throw new InvalidArgumentException(sprintf('Unsupported scoring metric: %s', $this->scoring));
    }

    /**
     * @param array<string, array<int, scalar>> $grid
     * @return array<int, array<string, scalar|array<int, scalar>>>
     */
    private static function expandGrid(array $grid): array
    {
        $result = [[]];

        foreach ($grid as $key => $values) {
            $new = [];
            foreach ($result as $combination) {
                foreach ($values as $value) {
                    $next = $combination;
                    $next[$key] = $value;
                    $new[] = $next;
                }
            }
            $result = $new;
        }

        return $result;
    }

    /**
     * @param array<int, array<int, float|int>> $samples
     * @param array<int, int|float|string|bool> $labels
     * @param array<int, int> $trainIdx
     * @param array<int, int> $testIdx
     * @return array{0: array<int, array<int, float|int>>, 1: array<int, int|float|string|bool>, 2: array<int, array<int, float|int>>, 3: array<int, int|float|string|bool>}
     */
    private static function buildFoldData(array $samples, array $labels, array $trainIdx, array $testIdx): array
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
}
