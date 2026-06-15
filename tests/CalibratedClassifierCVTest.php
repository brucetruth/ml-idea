<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Calibration\CalibratedClassifierCV;
use ML\IDEA\Classifiers\LogisticRegression;
use PHPUnit\Framework\TestCase;

final class CalibratedClassifierCVTest extends TestCase
{
    public function testCalibratedClassifierProducesProbabilities(): void
    {
        $samples = [[0, 0], [0, 1], [1, 0], [1, 1], [0.8, 0.9], [0.1, 0.2]];
        $labels = [0, 1, 1, 1, 1, 0];

        $base = new LogisticRegression(learningRate: 0.2, iterations: 1500);
        $cal = new CalibratedClassifierCV($base, cv: 3, iterations: 200, learningRate: 0.1);
        $cal->fit($samples, $labels);

        $proba = $cal->predictProba([1, 1]);
        self::assertCount(2, $proba);
        self::assertGreaterThanOrEqual(0.0, array_values($proba)[0]);
        self::assertLessThanOrEqual(1.0, array_values($proba)[0]);
    }

    public function testPrefitModeCalibratesWithoutRetrainingBase(): void
    {
        $calibrationX = [[0, 0], [0, 1], [1, 0], [3, 3], [3, 4], [4, 3]];
        $calibrationY = [0, 0, 0, 1, 1, 1];

        $base = new LogisticRegression(iterations: 600);
        $base->train($calibrationX, $calibrationY);

        $cal = new CalibratedClassifierCV($base, cv: 'prefit', iterations: 200);
        $cal->train($calibrationX, $calibrationY);

        $proba = $cal->predictProba([3.5, 3.5]);
        self::assertEqualsWithDelta(1.0, array_sum($proba), 0.01);
        self::assertGreaterThan(0.5, $proba[1] ?? 0.0);
    }
}
