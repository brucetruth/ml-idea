<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Clustering\MiniBatchKMeans;
use PHPUnit\Framework\TestCase;

final class MiniBatchKMeansTest extends TestCase
{
    public function testCanFitAndPredictClusters(): void
    {
        $samples = [
            [0.0, 0.1],
            [0.1, 0.0],
            [5.0, 5.1],
            [5.2, 5.0],
        ];

        $kmeans = new MiniBatchKMeans(k: 2, maxIterations: 50, batchSize: 2, seed: 42);
        $kmeans->fit($samples);

        $a = $kmeans->predict([0.05, 0.05]);
        $b = $kmeans->predict([5.1, 5.1]);

        self::assertNotSame($a, $b);
    }
}
