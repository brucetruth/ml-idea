<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Calibration\ThresholdTuner;
use PHPUnit\Framework\TestCase;

final class ThresholdTunerTest extends TestCase
{
    public function testOptimizeAndApplyThreshold(): void
    {
        $truth = [1, 0, 1, 0, 1, 0];
        $scores = [0.9, 0.6, 0.8, 0.3, 0.7, 0.2];

        $threshold = ThresholdTuner::optimize($truth, $scores, 1, metric: 'f1', steps: 21);
        self::assertGreaterThanOrEqual(0.0, $threshold);
        self::assertLessThanOrEqual(1.0, $threshold);

        $pred = ThresholdTuner::apply($scores, $threshold, 1, 0);
        self::assertCount(count($truth), $pred);
    }
}
