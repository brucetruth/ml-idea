<?php

declare(strict_types=1);

namespace ML\IDEA\Ensemble;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Exceptions\ModelNotTrainedException;
use ML\IDEA\Support\Assert;

/** CART decision tree for classification or regression. */
final class DecisionTree
{
    /** @var array<string, mixed>|null */
    private ?array $root = null;

    private int $featureCount = 0;
    private bool $trained = false;

    public function __construct(
        private readonly int $maxDepth = 3,
        private readonly int $minSamplesSplit = 2,
        private readonly string $mode = 'classification',
        private readonly ?int $seed = null,
    ) {
        if ($this->maxDepth <= 0) {
            throw new InvalidArgumentException('maxDepth must be positive.');
        }
        if ($this->minSamplesSplit < 2) {
            throw new InvalidArgumentException('minSamplesSplit must be at least 2.');
        }
        if (!in_array($this->mode, ['classification', 'regression'], true)) {
            throw new InvalidArgumentException('mode must be classification or regression.');
        }
    }

    /**
     * @param array<int, array<int, float|int>> $samples
     * @param array<int, int|float|string|bool> $targets
     * @param array<int, int>|null $featureSubset
     */
    public function fit(array $samples, array $targets, ?array $featureSubset = null): void
    {
        Assert::numericMatrix($samples);
        if ($targets === [] || count($targets) !== count($samples)) {
            throw new InvalidArgumentException('Targets must match sample count.');
        }

        $this->featureCount = count($samples[0]);
        $features = $featureSubset ?? range(0, $this->featureCount - 1);

        if ($this->seed !== null) {
            mt_srand($this->seed);
        }

        $this->root = $this->buildNode($samples, $targets, $features, 0);
        $this->trained = true;
    }

    public function predict(array $sample): int|float|string|bool
    {
        if (!$this->trained || $this->root === null) {
            throw new ModelNotTrainedException('DecisionTree has not been trained yet.');
        }

        Assert::sampleMatchesDimension($sample, $this->featureCount);

        return $this->walk($this->root, $sample);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'maxDepth' => $this->maxDepth,
            'minSamplesSplit' => $this->minSamplesSplit,
            'mode' => $this->mode,
            'seed' => $this->seed,
            'featureCount' => $this->featureCount,
            'root' => $this->root,
            'trained' => $this->trained,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $tree = new self(
            (int) ($data['maxDepth'] ?? 3),
            (int) ($data['minSamplesSplit'] ?? 2),
            (string) ($data['mode'] ?? 'classification'),
            isset($data['seed']) ? (int) $data['seed'] : null,
        );
        $tree->featureCount = (int) ($data['featureCount'] ?? 0);
        $tree->root = is_array($data['root'] ?? null) ? $data['root'] : null;
        $tree->trained = (bool) ($data['trained'] ?? false);

        return $tree;
    }

    /**
     * @param array<int, array<int, float|int>> $samples
     * @param array<int, int|float|string|bool> $targets
     * @param array<int, int> $features
     * @return array<string, mixed>
     */
    private function buildNode(array $samples, array $targets, array $features, int $depth): array
    {
        if ($this->mode === 'classification' && $this->isPure($targets)) {
            return ['leaf' => true, 'value' => $targets[0]];
        }

        if ($depth >= $this->maxDepth || count($samples) < $this->minSamplesSplit) {
            return ['leaf' => true, 'value' => $this->leafValue($targets)];
        }

        $best = $this->bestSplit($samples, $targets, $features);
        if ($best === null) {
            return ['leaf' => true, 'value' => $this->leafValue($targets)];
        }

        [$feature, $threshold, $leftSamples, $leftTargets, $rightSamples, $rightTargets] = $best;

        return [
            'leaf' => false,
            'feature' => $feature,
            'threshold' => $threshold,
            'left' => $this->buildNode($leftSamples, $leftTargets, $features, $depth + 1),
            'right' => $this->buildNode($rightSamples, $rightTargets, $features, $depth + 1),
        ];
    }

    /**
     * @param array<int, array<int, float|int>> $samples
     * @param array<int, int|float|string|bool> $targets
     * @param array<int, int> $features
     * @return array{0:int,1:float,2:array,3:array,4:array,5:array}|null
     */
    private function bestSplit(array $samples, array $targets, array $features): ?array
    {
        $bestScore = INF;
        $best = null;

        foreach ($features as $feature) {
            $thresholds = [];
            foreach ($samples as $row) {
                $thresholds[(string) (float) $row[$feature]] = (float) $row[$feature];
            }

            foreach ($thresholds as $threshold) {
                $leftSamples = $leftTargets = $rightSamples = $rightTargets = [];
                foreach ($samples as $i => $row) {
                    if ((float) $row[$feature] <= $threshold) {
                        $leftSamples[] = $row;
                        $leftTargets[] = $targets[$i];
                    } else {
                        $rightSamples[] = $row;
                        $rightTargets[] = $targets[$i];
                    }
                }

                if ($leftTargets === [] || $rightTargets === []) {
                    continue;
                }

                $score = $this->splitScore($leftTargets, $rightTargets);
                if ($score < $bestScore) {
                    $bestScore = $score;
                    $best = [$feature, $threshold, $leftSamples, $leftTargets, $rightSamples, $rightTargets];
                }
            }
        }

        return $best;
    }

    /** @param array<int, int|float|string|bool> $left @param array<int, int|float|string|bool> $right */
    private function splitScore(array $left, array $right): float
    {
        $n = count($left) + count($right);
        if ($this->mode === 'regression') {
            return (count($left) * $this->variance($left) + count($right) * $this->variance($right)) / $n;
        }

        return (count($left) * $this->gini($left) + count($right) * $this->gini($right)) / $n;
    }

    /** @param array<int, int|float|string|bool> $targets */
    private function leafValue(array $targets): int|float|string|bool
    {
        if ($this->mode === 'regression') {
            return array_sum(array_map('floatval', $targets)) / max(1, count($targets));
        }

        return $this->majority($targets);
    }

    /** @param array<int, int|float|string|bool> $targets */
    private function isPure(array $targets): bool
    {
        return count(array_unique($targets, SORT_REGULAR)) === 1;
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

    /** @param array<int, int|float|string|bool> $values */
    private function variance(array $values): float
    {
        $n = count($values);
        if ($n <= 1) {
            return 0.0;
        }

        $mean = array_sum(array_map('floatval', $values)) / $n;
        $sum = 0.0;
        foreach ($values as $value) {
            $delta = (float) $value - $mean;
            $sum += $delta * $delta;
        }

        return $sum / $n;
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

        return $map[(string) array_key_first($counts)];
    }

    /** @param array<string, mixed> $node */
    private function walk(array $node, array $sample): int|float|string|bool
    {
        if (($node['leaf'] ?? false) === true) {
            return $node['value'];
        }

        $feature = (int) $node['feature'];
        $threshold = (float) $node['threshold'];

        return ((float) $sample[$feature] <= $threshold)
            ? $this->walk($node['left'], $sample)
            : $this->walk($node['right'], $sample);
    }

    private function labelKey(int|float|string|bool $label): string
    {
        return get_debug_type($label) . ':' . json_encode($label, JSON_THROW_ON_ERROR);
    }
}
