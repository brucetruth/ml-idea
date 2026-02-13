<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Classifiers\LogisticRegression;
use ML\IDEA\Data\KFold;
use ML\IDEA\ModelSelection\CrossValidation;
use ML\IDEA\Regression\LinearRegression;
use PHPUnit\Framework\TestCase;

final class CrossValidationTest extends TestCase
{
    public function testCrossValScoreAndPredictForClassifier(): void
    {
        $samples = [[0, 0], [0, 1], [1, 0], [1, 1], [0.9, 0.9], [0.1, 0.2]];
        $labels = [0, 1, 1, 1, 1, 0];
        $folds = KFold::split(count($samples), 3, true, 42);

        $estimator = new LogisticRegression(learningRate: 0.2, iterations: 1500);
        $scores = CrossValidation::crossValScoreClassifier($estimator, $samples, $labels, $folds, 'accuracy');
        $pred = CrossValidation::crossValPredictClassifier($estimator, $samples, $labels, $folds);

        self::assertCount(3, $scores);
        self::assertCount(count($samples), $pred);
    }

    public function testCrossValScoreAndPredictForRegressor(): void
    {
        $samples = [[1.0], [2.0], [3.0], [4.0], [5.0], [6.0]];
        $targets = [2.0, 4.0, 6.1, 8.0, 10.0, 12.0];
        $folds = KFold::split(count($samples), 3, true, 42);

        $estimator = new LinearRegression(learningRate: 0.05, iterations: 3000);
        $scores = CrossValidation::crossValScoreRegressor($estimator, $samples, $targets, $folds, 'r2');
        $pred = CrossValidation::crossValPredictRegressor($estimator, $samples, $targets, $folds);

        self::assertCount(3, $scores);
        self::assertCount(count($samples), $pred);
    }
}
