<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\ModelSelection\GridSearchRegressor;
use ML\IDEA\Regression\LinearRegression;
use PHPUnit\Framework\TestCase;

final class GridSearchRegressorTest extends TestCase
{
    public function testFindsBestRegressorParams(): void
    {
        $samples = [[1.0], [2.0], [3.0], [4.0], [5.0], [6.0]];
        $targets = [2.0, 4.1, 5.9, 8.2, 10.1, 12.0];

        $search = new GridSearchRegressor(
            static fn (array $p): LinearRegression => new LinearRegression(
                learningRate: (float) $p['learningRate'],
                iterations: (int) $p['iterations'],
            ),
            scoring: 'r2',
            cv: 3,
        );

        $search->fit($samples, $targets, [
            'learningRate' => [0.01, 0.05],
            'iterations' => [1000, 3000],
        ]);

        self::assertGreaterThan(0.8, $search->bestScore());
        self::assertNotEmpty($search->bestParams());
        self::assertGreaterThan(11.0, $search->bestEstimator()->predict([6.0]));
    }
}
