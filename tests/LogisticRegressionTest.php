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
}
