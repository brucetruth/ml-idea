<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Ensemble\RandomForestClassifier;
use PHPUnit\Framework\TestCase;

final class RandomForestClassifierTest extends TestCase
{
    public function testCanClassifySimpleData(): void
    {
        $samples = [[1, 1], [1, 2], [2, 1], [4, 4], [5, 5], [4, 5]];
        $labels = ['A', 'A', 'A', 'B', 'B', 'B'];

        $rf = new RandomForestClassifier(nEstimators: 20, seed: 42);
        $rf->train($samples, $labels);

        self::assertSame('B', $rf->predict([4.8, 4.8]));
    }
}
