<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Dataset\Registry\DatasetIntegrity;
use PHPUnit\Framework\TestCase;

final class DatasetIntegrityTest extends TestCase
{
    public function testSeedDatasetsArePresent(): void
    {
        $missing = DatasetIntegrity::missingSeedDatasets();

        self::assertSame([], $missing, 'Missing seed datasets: ' . implode(', ', $missing));
        self::assertTrue(DatasetIntegrity::seedsAreComplete());
    }

    public function testSeedReportIncludesExpectedEntries(): void
    {
        $report = DatasetIntegrity::seedReport();

        self::assertArrayHasKey('wordnet', $report);
        self::assertArrayHasKey('sentiment', $report);
        self::assertArrayHasKey('geo-cities', $report);
        self::assertTrue($report['wordnet']['exists'] ?? false);
    }
}
