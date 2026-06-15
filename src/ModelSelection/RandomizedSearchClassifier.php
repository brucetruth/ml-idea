<?php

declare(strict_types=1);

namespace ML\IDEA\ModelSelection;

use ML\IDEA\Contracts\ClassifierInterface;
use ML\IDEA\Contracts\ProbabilisticClassifierInterface;
use ML\IDEA\Data\KFold;
use ML\IDEA\Data\StratifiedKFold;
use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Metrics\ClassificationMetrics;

final class RandomizedSearchClassifier
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
        private readonly bool $stratified = true,
        private readonly ?int $seed = 42,
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
    public function fit(array $samples, array $labels, array $paramGrid, int $nIter = 10): void
    {
        if ($samples === [] || $labels === [] || count($samples) !== count($labels)) {
            throw new InvalidArgumentException('Samples and labels must be non-empty and have same length.');
        }
        if ($nIter <= 0) {
            throw new InvalidArgumentException('nIter must be positive.');
        }

        $combinations = GridSearchClassifier::expandGrid($paramGrid);
        if ($this->seed !== null) {
            mt_srand($this->seed);
        }
        shuffle($combinations);
        $combinations = array_slice($combinations, 0, min($nIter, count($combinations)));

        $cv = $this->effectiveCv($samples, $labels);
        $folds = $this->stratified
            ? StratifiedKFold::split($labels, $cv, true, 42)
            : KFold::split(count($samples), $cv, true, 42);

        foreach ($combinations as $params) {
            $scores = [];
            foreach ($folds as $fold) {
                [$xTrain, $yTrain, $xTest, $yTest] = SearchScoring::buildFoldData($samples, $labels, $fold['train'], $fold['test']);
                $estimator = ($this->factory)($params);
                $estimator->train($xTrain, $yTrain);
                $predictions = $estimator->predictBatch($xTest);
                $scores[] = SearchScoring::score($this->scoring, $estimator, $yTest, $predictions, $xTest);
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

    /**
     * @param array<int, array<int, float|int>> $samples
     * @param array<int, int|float|string|bool> $labels
     */
    private function effectiveCv(array $samples, array $labels): int
    {
        $nSamples = count($samples);
        $cv = min($this->cv, $nSamples);

        if ($this->stratified) {
            $cv = min($cv, StratifiedKFold::maxSplits($labels));
        }

        if ($cv <= 1) {
            throw new InvalidArgumentException('Not enough samples for cross-validation.');
        }

        return $cv;
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
            throw new InvalidArgumentException('Randomized search has not been fitted yet.');
        }

        return $this->bestEstimator;
    }
}
