<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Neural\MLPBinaryClassifier;
use PHPUnit\Framework\TestCase;

final class MLPBinaryClassifierTest extends TestCase
{
    public function testLearnsSimpleBoundary(): void
    {
        $samples = [[0, 0], [0, 1], [1, 0], [1, 1]];
        $labels = [0, 0, 0, 1];

        $mlp = new MLPBinaryClassifier(hiddenUnits: 8, epochs: 1000, learningRate: 0.1, seed: 42);
        $mlp->train($samples, $labels);

        self::assertSame(1, $mlp->predict([1, 1]));
        self::assertSame(0, $mlp->predict([0, 0]));
    }
}
