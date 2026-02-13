<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Metrics\RegressionMetrics;
use ML\IDEA\Regression\LinearRegression;
use PHPUnit\Framework\TestCase;

final class LinearRegressionTest extends TestCase
{
    public function testFitsSimpleLinearTrend(): void
    {
        $samples = [[1.0], [2.0], [3.0], [4.0], [5.0]];
        $targets = [2.0, 4.0, 6.0, 8.0, 10.0];

        $model = new LinearRegression(learningRate: 0.05, iterations: 5000);
        $model->train($samples, $targets);

        $predictions = $model->predictBatch($samples);
        $mse = RegressionMetrics::meanSquaredError($targets, $predictions);

        self::assertLessThan(0.01, $mse);
        self::assertEqualsWithDelta(12.0, $model->predict([6.0]), 0.2);
    }
}
