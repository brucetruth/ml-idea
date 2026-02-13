<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Ensemble\RandomForestRegressor;
use PHPUnit\Framework\TestCase;

final class RandomForestRegressorTest extends TestCase
{
    public function testCanRegressSimpleTrend(): void
    {
        $samples = [[1.0], [2.0], [3.0], [4.0], [5.0], [6.0]];
        $targets = [2.0, 4.0, 6.1, 8.0, 10.1, 12.0];

        $rf = new RandomForestRegressor(nEstimators: 20, seed: 42);
        $rf->train($samples, $targets);

        self::assertGreaterThan(10.0, $rf->predict([5.8]));
    }
}
