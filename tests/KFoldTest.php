<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Data\KFold;
use PHPUnit\Framework\TestCase;

final class KFoldTest extends TestCase
{
    public function testSplitCreatesExpectedFoldCountAndCoverage(): void
    {
        $folds = KFold::split(10, 5, true, 42);

        self::assertCount(5, $folds);

        $allTest = [];
        foreach ($folds as $fold) {
            self::assertNotEmpty($fold['train']);
            self::assertNotEmpty($fold['test']);
            $allTest = array_merge($allTest, $fold['test']);
        }

        sort($allTest);
        self::assertSame(range(0, 9), $allTest);
    }
}
