<?php

declare(strict_types=1);

namespace ML\IDEA\ModelSelection;

use ML\IDEA\Contracts\RegressorInterface;
use ML\IDEA\Data\KFold;
use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Metrics\RegressionMetrics;

final class GridSearchRegressor
{
    private readonly \Closure $factory;

    /** @var array<string, scalar|array<int, scalar>> */
    private array $bestParams = [];

    private float $bestScore = -INF;
    private ?RegressorInterface $bestEstimator = null;

    /**
     * @param callable(array<string, scalar|array<int, scalar>>): RegressorInterface $factory
     */
    public function __construct(
        callable $factory,
        private readonly string $scoring = 'r2',
        private readonly int $cv = 5,
    ) {
        $this->factory = \Closure::fromCallable($factory);

        if ($this->cv <= 1) {
            throw new InvalidArgumentException('cv must be greater than 1.');
        }
    }

    /**
     * @param array<int, array<int, float|int>> $samples
     * @param array<int, float|int> $targets
     * @param array<string, array<int, scalar>> $paramGrid
     */
    public function fit(array $samples, array $targets, array $paramGrid): void
    {
        if ($samples === [] || $targets === [] || count($samples) !== count($targets)) {
            throw new InvalidArgumentException('Samples and targets must be non-empty and have same length.');
        }

        $paramCombinations = self::expandGrid($paramGrid);
        foreach ($paramCombinations as $params) {
            $scores = [];
            $folds = KFold::split(count($samples), $this->cv, true, 42);

            foreach ($folds as $fold) {
                [$xTrain, $yTrain, $xTest, $yTest] = self::buildFoldData($samples, $targets, $fold['train'], $fold['test']);
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
        $this->bestEstimator->train($samples, $targets);
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

    public function bestEstimator(): RegressorInterface
    {
        if ($this->bestEstimator === null) {
            throw new InvalidArgumentException('Grid search has not been fitted yet.');
        }

        return $this->bestEstimator;
    }

    /**
     * @param array<int, float|int> $truth
     * @param array<int, float|int> $predictions
     */
    private function score(array $truth, array $predictions): float
    {
        return match ($this->scoring) {
            'r2' => RegressionMetrics::r2Score($truth, $predictions),
            'neg_rmse' => -RegressionMetrics::rootMeanSquaredError($truth, $predictions),
            default => throw new InvalidArgumentException(sprintf('Unsupported scoring metric: %s', $this->scoring)),
        };
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
     * @param array<int, float|int> $targets
     * @param array<int, int> $trainIdx
     * @param array<int, int> $testIdx
     * @return array{0: array<int, array<int, float|int>>, 1: array<int, float|int>, 2: array<int, array<int, float|int>>, 3: array<int, float|int>}
     */
    private static function buildFoldData(array $samples, array $targets, array $trainIdx, array $testIdx): array
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
