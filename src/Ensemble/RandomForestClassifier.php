<?php

declare(strict_types=1);

namespace ML\IDEA\Ensemble;

use ML\IDEA\Classifiers\AbstractClassifier;
use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Exceptions\ModelNotTrainedException;
use ML\IDEA\Support\Assert;

final class RandomForestClassifier extends AbstractClassifier
{
    /** @var array<int, array{feature:int, threshold:float, left:int|float|string|bool, right:int|float|string|bool}> */
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

    public function train(array $samples, array $labels): void
    {
        Assert::numericMatrix($samples);
        Assert::matchingSampleLabelCount($samples, $labels);

        $nSamples = count($samples);
        $this->featureCount = count($samples[0]);
        $maxFeatures = $this->maxFeatures ?? max(1, (int) floor(sqrt($this->featureCount)));
        $maxFeatures = min($maxFeatures, $this->featureCount);

        if ($this->seed !== null) {
            mt_srand($this->seed);
        }

        $this->trees = [];
        for ($t = 0; $t < $this->nEstimators; $t++) {
            $bootstrapSamples = [];
            $bootstrapLabels = [];
            for ($i = 0; $i < $nSamples; $i++) {
                $idx = mt_rand(0, $nSamples - 1);
                $bootstrapSamples[] = $samples[$idx];
                $bootstrapLabels[] = $labels[$idx];
            }

            $features = range(0, $this->featureCount - 1);
            shuffle($features);
            $features = array_slice($features, 0, $maxFeatures);

            $this->trees[] = $this->bestStump($bootstrapSamples, $bootstrapLabels, $features);
        }

        $this->trained = true;
    }

    public function predict(array $sample): int|float|string|bool
    {
        if (!$this->trained) {
            throw new ModelNotTrainedException('RandomForestClassifier has not been trained yet.');
        }

        Assert::sampleMatchesDimension($sample, $this->featureCount);

        $votes = [];
        foreach ($this->trees as $tree) {
            $prediction = ((float) $sample[$tree['feature']] <= $tree['threshold']) ? $tree['left'] : $tree['right'];
            $key = $this->labelKey($prediction);
            $votes[$key] = ($votes[$key] ?? ['label' => $prediction, 'count' => 0]);
            $votes[$key]['count']++;
        }

        usort($votes, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);
        return $votes[0]['label'];
    }

    /**
     * @param array<int, array<int, float|int>> $samples
     * @param array<int, int|float|string|bool> $labels
     * @param array<int, int> $features
     * @return array{feature:int, threshold:float, left:int|float|string|bool, right:int|float|string|bool}
     */
    private function bestStump(array $samples, array $labels, array $features): array
    {
        $bestFeature = $features[0];
        $bestThreshold = (float) $samples[0][$bestFeature];
        $bestScore = INF;
        $bestLeft = $labels[0];
        $bestRight = $labels[0];

        foreach ($features as $feature) {
            $thresholds = array_map(static fn (array $row): float => (float) $row[$feature], $samples);
            $thresholds = array_values(array_unique($thresholds));

            foreach ($thresholds as $threshold) {
                $left = $right = [];
                foreach ($samples as $i => $row) {
                    if ((float) $row[$feature] <= $threshold) {
                        $left[] = $labels[$i];
                    } else {
                        $right[] = $labels[$i];
                    }
                }

                if ($left === [] || $right === []) {
                    continue;
                }

                $score = (count($left) * $this->gini($left) + count($right) * $this->gini($right)) / count($samples);
                if ($score < $bestScore) {
                    $bestScore = $score;
                    $bestFeature = $feature;
                    $bestThreshold = $threshold;
                    $bestLeft = $this->majority($left);
                    $bestRight = $this->majority($right);
                }
            }
        }

        return ['feature' => $bestFeature, 'threshold' => $bestThreshold, 'left' => $bestLeft, 'right' => $bestRight];
    }

    /** @param array<int, int|float|string|bool> $labels */
    private function gini(array $labels): float
    {
        $counts = [];
        foreach ($labels as $label) {
            $key = $this->labelKey($label);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        $n = count($labels);
        $sum = 0.0;
        foreach ($counts as $count) {
            $p = $count / $n;
            $sum += $p * $p;
        }

        return 1.0 - $sum;
    }

    /** @param array<int, int|float|string|bool> $labels */
    private function majority(array $labels): int|float|string|bool
    {
        $counts = [];
        $map = [];
        foreach ($labels as $label) {
            $key = $this->labelKey($label);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
            $map[$key] = $label;
        }

        arsort($counts);
        $key = (string) array_key_first($counts);
        return $map[$key];
    }

    private function labelKey(int|float|string|bool $label): string
    {
        return get_debug_type($label) . ':' . json_encode($label, JSON_THROW_ON_ERROR);
    }
}
