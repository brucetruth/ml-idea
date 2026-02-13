<?php

declare(strict_types=1);

namespace ML\IDEA\Clustering;

use ML\IDEA\Contracts\ClustererInterface;
use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Exceptions\ModelNotTrainedException;
use ML\IDEA\Math\Distance;
use ML\IDEA\Support\Assert;

final class MiniBatchKMeans implements ClustererInterface
{
    /** @var array<int, array<int, float>> */
    private array $centroids = [];

    private int $featureCount = 0;
    private bool $fitted = false;

    public function __construct(
        private readonly int $k = 8,
        private readonly int $maxIterations = 100,
        private readonly int $batchSize = 32,
        private readonly ?int $seed = 42,
    ) {
        if ($this->k <= 1) {
            throw new InvalidArgumentException('k must be greater than 1.');
        }
        if ($this->maxIterations <= 0 || $this->batchSize <= 0) {
            throw new InvalidArgumentException('maxIterations and batchSize must be positive.');
        }
    }

    public function fit(array $samples): void
    {
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

        $counts = array_fill(0, $this->k, 0);

        for ($iter = 0; $iter < $this->maxIterations; $iter++) {
            $batchIndices = $indices;
            shuffle($batchIndices);
            $batchIndices = array_slice($batchIndices, 0, min($this->batchSize, $nSamples));

            foreach ($batchIndices as $index) {
                $sample = $samples[$index];
                $cluster = $this->nearestCentroid($sample);
                $counts[$cluster]++;
                $eta = 1.0 / $counts[$cluster];

                foreach ($sample as $j => $value) {
                    $x = (float) $value;
                    $this->centroids[$cluster][$j] = (1.0 - $eta) * $this->centroids[$cluster][$j] + ($eta * $x);
                }
            }
        }

        $this->fitted = true;
    }

    public function predict(array $sample): int
    {
        if (!$this->fitted) {
            throw new ModelNotTrainedException('MiniBatchKMeans must be fitted before predict.');
        }

        Assert::sampleMatchesDimension($sample, $this->featureCount);
        return $this->nearestCentroid($sample);
    }

    public function predictBatch(array $samples): array
    {
        if (!$this->fitted) {
            throw new ModelNotTrainedException('MiniBatchKMeans must be fitted before predictBatch.');
        }

        Assert::numericMatrix($samples);
        $predictions = [];
        foreach ($samples as $sample) {
            $predictions[] = $this->predict($sample);
        }

        return $predictions;
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
}
