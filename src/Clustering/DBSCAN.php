<?php

declare(strict_types=1);

namespace ML\IDEA\Clustering;

use ML\IDEA\Contracts\ClustererInterface;
use ML\IDEA\Data\SparseVector;
use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Math\Distance;
use ML\IDEA\Support\Assert;

/** Density-based clustering; noise points receive label -1. */
final class DBSCAN implements ClustererInterface
{
    private const UNVISITED = -2;
    private const NOISE = -1;

    /** @var array<int, int> */
    private array $labels = [];

    /** @var array<int, array<int, float|int>> */
    private array $samples = [];

    private bool $fitted = false;

    public function __construct(
        private readonly float $eps = 0.5,
        private readonly int $minSamples = 5,
    ) {
        if ($this->eps <= 0.0) {
            throw new InvalidArgumentException('eps must be positive.');
        }
        if ($this->minSamples <= 0) {
            throw new InvalidArgumentException('minSamples must be positive.');
        }
    }

    /**
     * @param array<int, array<int, float|int>> $samples
     */
    public function fit(array $samples): void
    {
        $this->fitPredict($samples);
    }

    /**
     * @param array<int, array<int, float|int>> $samples
     * @return array<int, int> cluster id per sample (-1 = noise)
     */
    public function fitPredict(array $samples): array
    {
        $samples = SparseVector::prepareDenseMatrix($samples);
        Assert::numericMatrix($samples);
        $this->samples = $samples;
        $n = count($samples);

        $this->labels = array_fill(0, $n, self::UNVISITED);
        $clusterId = 0;

        for ($i = 0; $i < $n; $i++) {
            if ($this->labels[$i] !== self::UNVISITED) {
                continue;
            }

            $neighbors = $this->regionQuery($i);
            if (count($neighbors) < $this->minSamples) {
                $this->labels[$i] = self::NOISE;
                continue;
            }

            $this->expandCluster($i, $neighbors, $clusterId);
            $clusterId++;
        }

        $this->fitted = true;

        return $this->labels;
    }

    /** @return array<int, int> */
    public function labels(): array
    {
        if (!$this->fitted) {
            throw new InvalidArgumentException('DBSCAN must be fitted before labels().');
        }

        return $this->labels;
    }

    /**
     * Assign a new point to the nearest non-noise cluster centroid, or -1.
     *
     * @param array<int, float|int> $sample
     */
    public function predict(array $sample): int
    {
        if (!$this->fitted) {
            throw new InvalidArgumentException('DBSCAN must be fitted before predict.');
        }

        Assert::sampleMatchesDimension($sample, count($this->samples[0]));

        $clusters = [];
        foreach ($this->labels as $i => $label) {
            if ($label < 0) {
                continue;
            }
            $clusters[$label][] = $this->samples[$i];
        }

        if ($clusters === []) {
            return self::NOISE;
        }

        $bestCluster = self::NOISE;
        $bestDistance = INF;

        foreach ($clusters as $clusterId => $members) {
            $centroid = $this->centroid($members);
            $distance = Distance::euclidean($sample, $centroid);
            if ($distance < $bestDistance && $distance <= $this->eps * 2) {
                $bestDistance = $distance;
                $bestCluster = $clusterId;
            }
        }

        return $bestCluster;
    }

    public function predictBatch(array $samples): array
    {
        if (!$this->fitted) {
            throw new InvalidArgumentException('DBSCAN must be fitted before predictBatch.');
        }

        return array_map(fn (array $sample): int => $this->predict($sample), $samples);
    }

    /** @param array<int, int> $seedNeighbors */
    private function expandCluster(int $pointIndex, array $seedNeighbors, int $clusterId): void
    {
        $this->labels[$pointIndex] = $clusterId;
        $queue = $seedNeighbors;

        while ($queue !== []) {
            $current = array_shift($queue);

            if ($this->labels[$current] === self::NOISE) {
                $this->labels[$current] = $clusterId;
            }

            if ($this->labels[$current] !== self::UNVISITED) {
                continue;
            }

            $this->labels[$current] = $clusterId;
            $neighbors = $this->regionQuery($current);

            if (count($neighbors) >= $this->minSamples) {
                foreach ($neighbors as $neighbor) {
                    if ($this->labels[$neighbor] === self::UNVISITED || $this->labels[$neighbor] === self::NOISE) {
                        $queue[] = $neighbor;
                    }
                }
            }
        }
    }

    /** @return array<int, int> indices within eps */
    private function regionQuery(int $index): array
    {
        $neighbors = [];
        foreach ($this->samples as $i => $sample) {
            if (Distance::euclidean($this->samples[$index], $sample) <= $this->eps) {
                $neighbors[] = $i;
            }
        }

        return $neighbors;
    }

    /** @param array<int, array<int, float|int>> $members @return array<int, float> */
    private function centroid(array $members): array
    {
        $dims = count($members[0]);
        $sum = array_fill(0, $dims, 0.0);
        foreach ($members as $member) {
            foreach ($member as $j => $value) {
                $sum[$j] += (float) $value;
            }
        }

        $n = count($members);

        return array_map(static fn (float $v): float => $v / $n, $sum);
    }
}
