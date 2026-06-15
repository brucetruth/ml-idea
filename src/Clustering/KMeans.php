<?php

declare(strict_types=1);

namespace ML\IDEA\Clustering;

use ML\IDEA\Contracts\ClustererInterface;
use ML\IDEA\Data\SparseVector;
use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Exceptions\ModelNotTrainedException;
use ML\IDEA\Math\Distance;
use ML\IDEA\Support\Assert;

/** Full-batch Lloyd K-means (sklearn-compatible baseline). */
final class KMeans implements ClustererInterface
{
    /** @var array<int, array<int, float>> */
    private array $centroids = [];

    private int $featureCount = 0;
    private bool $fitted = false;

    public function __construct(
        private readonly int $k = 8,
        private readonly int $maxIterations = 300,
        private readonly ?int $seed = 42,
    ) {
        if ($this->k <= 1) {
            throw new InvalidArgumentException('k must be greater than 1.');
        }
        if ($this->maxIterations <= 0) {
            throw new InvalidArgumentException('maxIterations must be positive.');
        }
    }

    public function fit(array $samples): void
    {
        $samples = $this->prepareSamples($samples);
        Assert::numericMatrix($samples);
        $nSamples = count($samples);
        $this->featureCount = count($samples[0]);

        if ($this->k > $nSamples) {
            throw new InvalidArgumentException('k cannot exceed number of samples.');
        }

        $indices = array_keys($samples);
        if ($this->seed !== null) {
            mt_srand($this->seed);
        }
        shuffle($indices);

        $this->centroids = [];
        for ($i = 0; $i < $this->k; $i++) {
            $this->centroids[] = array_map(static fn ($v): float => (float) $v, $samples[$indices[$i]]);
        }

        $assignments = array_fill(0, $nSamples, 0);

        for ($iter = 0; $iter < $this->maxIterations; $iter++) {
            $changed = false;

            for ($i = 0; $i < $nSamples; $i++) {
                $cluster = $this->nearestCentroid($samples[$i]);
                if ($assignments[$i] !== $cluster) {
                    $assignments[$i] = $cluster;
                    $changed = true;
                }
            }

            if (!$changed && $iter > 0) {
                break;
            }

            $newCentroids = array_fill(0, $this->k, array_fill(0, $this->featureCount, 0.0));
            $counts = array_fill(0, $this->k, 0);

            for ($i = 0; $i < $nSamples; $i++) {
                $cluster = $assignments[$i];
                $counts[$cluster]++;
                foreach ($samples[$i] as $j => $value) {
                    $newCentroids[$cluster][$j] += (float) $value;
                }
            }

            for ($c = 0; $c < $this->k; $c++) {
                if ($counts[$c] === 0) {
                    $newCentroids[$c] = $this->centroids[$c];
                    continue;
                }
                foreach ($newCentroids[$c] as $j => $sum) {
                    $newCentroids[$c][$j] = $sum / $counts[$c];
                }
            }

            $this->centroids = $newCentroids;
        }

        $this->fitted = true;
    }

    public function predict(array $sample): int
    {
        if (!$this->fitted) {
            throw new ModelNotTrainedException('KMeans must be fitted before predict.');
        }

        $sample = $this->prepareSample($sample);
        Assert::sampleMatchesDimension($sample, $this->featureCount);

        return $this->nearestCentroid($sample);
    }

    public function predictBatch(array $samples): array
    {
        $samples = $this->prepareSamples($samples);
        Assert::numericMatrix($samples);

        return array_map(fn (array $sample): int => $this->predict($sample), $samples);
    }

    /** @return array<int, array<int, float>> */
    public function centroids(): array
    {
        if (!$this->fitted) {
            throw new ModelNotTrainedException('KMeans must be fitted before centroids().');
        }

        return $this->centroids;
    }

    /** @param array<int, float|int> $sample */
    private function nearestCentroid(array $sample): int
    {
        $bestCluster = 0;
        $bestDistance = INF;

        foreach ($this->centroids as $i => $centroid) {
            $distance = Distance::euclidean($sample, $centroid);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $bestCluster = $i;
            }
        }

        return $bestCluster;
    }

    /**
     * @param array<int, array<int, float|int>> $samples
     * @return array<int, array<int, float|int>>
     */
    private function prepareSamples(array $samples): array
    {
        return SparseVector::prepareDenseMatrix($samples);
    }

    /** @param array<int, float|int> $sample @return array<int, float|int> */
    private function prepareSample(array $sample): array
    {
        if (!SparseVector::isSparseRow($sample, $this->featureCount > 0 ? $this->featureCount : null)) {
            return $sample;
        }

        $dims = $this->featureCount > 0
            ? $this->featureCount
            : (empty($sample) ? 0 : max(array_keys($sample)) + 1);

        return SparseVector::toDense($sample, $dims);
    }
}
