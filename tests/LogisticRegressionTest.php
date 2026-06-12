<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Classifiers\LogisticRegression;
use PHPUnit\Framework\TestCase;

final class LogisticRegressionTest extends TestCase
{
    public function testLearnsSimpleOrLikeBoundary(): void
    {
        $samples = [[0, 0], [0, 1], [1, 0], [1, 1]];
        $labels = [0, 1, 1, 1];

        $model = new LogisticRegression(learningRate: 0.2, iterations: 2000);
        $model->train($samples, $labels);

        self::assertSame(1, $model->predict([1, 1]));
        self::assertSame(0, $model->predict([0, 0]));
    }

    public function testLearnsSimpleMulticlassClusters(): void
    {
        $samples = [
            [0.0, 0.0], [0.2, -0.1], [-0.1, 0.2],
            [3.0, 3.0], [2.8, 3.1], [3.2, 2.9],
            [-3.0, 3.0], [-2.9, 2.8], [-3.2, 3.1],
        ];
        $labels = ['red', 'red', 'red', 'green', 'green', 'green', 'blue', 'blue', 'blue'];

        $model = new LogisticRegression(learningRate: 0.1, iterations: 2500);
        $model->train($samples, $labels);

        self::assertSame('red', $model->predict([0.1, 0.0]));
        self::assertSame('green', $model->predict([3.1, 3.0]));
        self::assertSame('blue', $model->predict([-3.1, 3.0]));
    }

    public function testPredictProbaReturnsNormalizedDistributionForMulticlass(): void
    {
        $samples = [
            [0.0, 0.0], [0.2, -0.1], [-0.1, 0.2],
            [3.0, 3.0], [2.8, 3.1], [3.2, 2.9],
            [-3.0, 3.0], [-2.9, 2.8], [-3.2, 3.1],
        ];
        $labels = ['red', 'red', 'red', 'green', 'green', 'green', 'blue', 'blue', 'blue'];

        $model = new LogisticRegression(learningRate: 0.1, iterations: 2500);
        $model->train($samples, $labels);

        $proba = $model->predictProba([0.1, 0.0]);

        self::assertCount(3, $proba);
        self::assertArrayHasKey('red', $proba);
        self::assertArrayHasKey('green', $proba);
        self::assertArrayHasKey('blue', $proba);

        $sum = array_sum($proba);
        self::assertEqualsWithDelta(1.0, $sum, 1e-9);
        self::assertGreaterThan($proba['green'], $proba['red']);
        self::assertGreaterThan($proba['blue'], $proba['red']);
    }
}
