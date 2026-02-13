<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Data\StratifiedKFold;
use ML\IDEA\Data\TimeSeriesSplit;
use PHPUnit\Framework\TestCase;

final class SplittersAdvancedTest extends TestCase
{
    public function testStratifiedKFoldCreatesBalancedSplits(): void
    {
        $labels = [0, 0, 0, 0, 1, 1, 1, 1];
        $folds = StratifiedKFold::split($labels, nSplits: 4, shuffle: true, seed: 42);

        self::assertCount(4, $folds);
        foreach ($folds as $fold) {
            self::assertNotEmpty($fold['train']);
            self::assertNotEmpty($fold['test']);
        }
    }

    public function testTimeSeriesSplitRespectsTemporalOrder(): void
    {
        $folds = TimeSeriesSplit::split(12, nSplits: 3, testSize: 2);
        self::assertCount(3, $folds);

        foreach ($folds as $fold) {
            self::assertLessThan(min($fold['test']), max($fold['train']));
        }
    }
}
