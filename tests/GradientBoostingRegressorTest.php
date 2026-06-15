<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Ensemble\GradientBoostingRegressor;
use ML\IDEA\Metrics\RegressionMetrics;
use PHPUnit\Framework\TestCase;

final class GradientBoostingRegressorTest extends TestCase
{
    public function testCanRegressSimpleTrend(): void
    {
        $samples = [[1.0], [2.0], [3.0], [4.0], [5.0], [6.0]];
        $targets = [2.0, 4.0, 6.1, 8.0, 10.1, 12.0];

        $gb = new GradientBoostingRegressor(nEstimators: 50, learningRate: 0.2, maxDepth: 2, seed: 42);
        $gb->train($samples, $targets);

        self::assertGreaterThan(10.0, $gb->predict([5.8]));

        $roundTrip = GradientBoostingRegressor::fromArray($gb->toArray());
        self::assertEqualsWithDelta($gb->predict([3.0]), $roundTrip->predict([3.0]), 0.01);
    }

    public function testBeatsBaselineRmseOnQuadratic(): void
    {
        $samples = [[0.0], [1.0], [2.0], [3.0], [4.0], [5.0]];
        $targets = array_map(static fn (float $x): float => $x * $x, [0.0, 1.0, 2.0, 3.0, 4.0, 5.0]);

        $gb = new GradientBoostingRegressor(nEstimators: 80, learningRate: 0.15, maxDepth: 3, seed: 42);
        $gb->train($samples, $targets);
        $pred = $gb->predictBatch($samples);

        self::assertLessThan(1.0, RegressionMetrics::rootMeanSquaredError($targets, $pred));
    }
}
