<?php

declare(strict_types=1);

namespace ML\IDEA\Ensemble;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Exceptions\ModelNotTrainedException;
use ML\IDEA\Regression\AbstractRegressor;
use ML\IDEA\Support\Assert;

final class RandomForestRegressor extends AbstractRegressor
{
    /** @var array<int, array{feature:int, threshold:float, left:float, right:float}> */
    private array $trees = [];

    private int $featureCount = 0;
    private bool $trained = false;

    public function __construct(
        private readonly int $nEstimators = 50,
        private readonly ?int $maxFeatures = null,
        private readonly ?int $seed = 42,
    ) {
        if ($this->nEstimators <= 0) {
            throw new InvalidArgumentException('nEstimators must be positive.');
        }
    }

    public function train(array $samples, array $targets): void
    {
        Assert::numericMatrix($samples);
        if ($targets === [] || count($samples) !== count($targets)) {
            throw new InvalidArgumentException('Targets must be non-empty and match sample count.');
        }

        $nSamples = count($samples);
        $this->featureCount = count($samples[0]);
        $maxFeatures = $this->maxFeatures ?? max(1, intdiv($this->featureCount, 3));
        $maxFeatures = min($maxFeatures, $this->featureCount);

        if ($this->seed !== null) {
            mt_srand($this->seed);
        }

        $this->trees = [];
        for ($t = 0; $t < $this->nEstimators; $t++) {
            $bootstrapSamples = [];
            $bootstrapTargets = [];
            for ($i = 0; $i < $nSamples; $i++) {
                $idx = mt_rand(0, $nSamples - 1);
                $bootstrapSamples[] = $samples[$idx];
                $bootstrapTargets[] = (float) $targets[$idx];
            }

            $features = range(0, $this->featureCount - 1);
            shuffle($features);
            $features = array_slice($features, 0, $maxFeatures);

            $this->trees[] = $this->bestStump($bootstrapSamples, $bootstrapTargets, $features);
        }

        $this->trained = true;
    }

    public function predict(array $sample): float
    {
        if (!$this->trained) {
            throw new ModelNotTrainedException('RandomForestRegressor has not been trained yet.');
        }

        Assert::sampleMatchesDimension($sample, $this->featureCount);

        $sum = 0.0;
        foreach ($this->trees as $tree) {
            $sum += ((float) $sample[$tree['feature']] <= $tree['threshold']) ? $tree['left'] : $tree['right'];
        }

        return $sum / count($this->trees);
    }

    /**
     * @param array<int, array<int, float|int>> $samples
     * @param array<int, float> $targets
     * @param array<int, int> $features
     * @return array{feature:int, threshold:float, left:float, right:float}
     */
    private function bestStump(array $samples, array $targets, array $features): array
    {
        $bestFeature = $features[0];
        $bestThreshold = (float) $samples[0][$bestFeature];
        $bestScore = INF;
        $bestLeft = $targets[0];
        $bestRight = $targets[0];

        foreach ($features as $feature) {
            $thresholds = array_map(static fn (array $row): float => (float) $row[$feature], $samples);
            $thresholds = array_values(array_unique($thresholds));

            foreach ($thresholds as $threshold) {
                $left = $right = [];
                foreach ($samples as $i => $row) {
                    if ((float) $row[$feature] <= $threshold) {
                        $left[] = $targets[$i];
                    } else {
                        $right[] = $targets[$i];
                    }
                }

                if ($left === [] || $right === []) {
                    continue;
                }

                $leftMean = array_sum($left) / count($left);
                $rightMean = array_sum($right) / count($right);

                $score = 0.0;
                foreach ($left as $value) {
                    $score += ($value - $leftMean) * ($value - $leftMean);
                }
                foreach ($right as $value) {
                    $score += ($value - $rightMean) * ($value - $rightMean);
                }
                $score /= count($samples);

                if ($score < $bestScore) {
                    $bestScore = $score;
                    $bestFeature = $feature;
                    $bestThreshold = $threshold;
                    $bestLeft = $leftMean;
                    $bestRight = $rightMean;
                }
            }
        }

        return ['feature' => $bestFeature, 'threshold' => $bestThreshold, 'left' => $bestLeft, 'right' => $bestRight];
    }
}
