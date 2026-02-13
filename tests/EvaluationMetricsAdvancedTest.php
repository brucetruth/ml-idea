<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Metrics\ClassificationMetrics;
use ML\IDEA\Metrics\RegressionMetrics;
use PHPUnit\Framework\TestCase;

final class EvaluationMetricsAdvancedTest extends TestCase
{
    public function testAdvancedClassificationMetricsReturnValidRanges(): void
    {
        $truth = [1, 0, 1, 0, 1, 0];
        $pred = [1, 0, 1, 1, 1, 0];
        $scores = [0.9, 0.2, 0.8, 0.6, 0.7, 0.1];

        $roc = ClassificationMetrics::rocAuc($truth, $scores, 1);
        $pr = ClassificationMetrics::prAuc($truth, $scores, 1);
        $logLoss = ClassificationMetrics::logLoss($truth, $scores, 1);
        $brier = ClassificationMetrics::brierScore($truth, $scores, 1);
        $mcc = ClassificationMetrics::matthewsCorrcoef($truth, $pred, 1);

        self::assertGreaterThanOrEqual(0.0, $roc);
        self::assertLessThanOrEqual(1.0, $roc);
        self::assertGreaterThanOrEqual(0.0, $pr);
        self::assertLessThanOrEqual(1.0, $pr);
        self::assertGreaterThanOrEqual(0.0, $logLoss);
        self::assertGreaterThanOrEqual(0.0, $brier);
        self::assertGreaterThanOrEqual(-1.0, $mcc);
        self::assertLessThanOrEqual(1.0, $mcc);
    }

    public function testMapeComputesValue(): void
    {
        $truth = [100.0, 200.0, 400.0];
        $pred = [110.0, 180.0, 420.0];

        $mape = RegressionMetrics::meanAbsolutePercentageError($truth, $pred);
        self::assertGreaterThan(0.0, $mape);
    }
}
