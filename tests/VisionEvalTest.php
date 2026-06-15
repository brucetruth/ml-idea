<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Vision\Classifiers\AuthenticityClassifier;
use ML\IDEA\Vision\Eval\VisionEval;
use PHPUnit\Framework\TestCase;

final class VisionEvalTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/fixtures/vision_authenticity_signals.json';

    public function testClassifierReportComputesRocAuc(): void
    {
        [$signals, $labels] = VisionEval::loadSignalFixtures(self::FIXTURE);

        $classifier = new AuthenticityClassifier();
        $classifier->train($signals, $labels);

        $report = VisionEval::classifierReport($classifier, $signals, $labels);

        self::assertGreaterThan(0.8, $report['roc_auc']);
        self::assertGreaterThan(0.8, $report['accuracy']);
        self::assertArrayHasKey('report', $report);
    }

    public function testHeuristicReportComputesMetrics(): void
    {
        [$signals, $labels] = VisionEval::loadSignalFixtures(self::FIXTURE);

        $report = VisionEval::heuristicReport($signals, $labels);

        self::assertGreaterThan(0.5, $report['roc_auc']);
        self::assertArrayHasKey('pr_auc', $report);
    }
}
