<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Decomposition\PCA;
use PHPUnit\Framework\TestCase;

final class PcaTest extends TestCase
{
    public function testFitTransformReducesDimensions(): void
    {
        $samples = [
            [2.5, 2.4, 1.0],
            [0.5, 0.7, 0.2],
            [2.2, 2.9, 0.9],
            [1.9, 2.2, 0.8],
        ];

        $pca = new PCA(nComponents: 2, powerIterations: 30);
        $projected = $pca->fitTransform($samples);

        self::assertCount(4, $projected);
        self::assertCount(2, $projected[0]);
    }
}
