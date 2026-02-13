<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Classifiers\KNearestNeighbors;
use PHPUnit\Framework\TestCase;

final class KNearestNeighborsTest extends TestCase
{
    public function testPredictReturnsExpectedClass(): void
    {
        $samples = [[1, 1], [1, 2], [4, 4], [5, 5]];
        $labels = ['A', 'A', 'B', 'B'];

        $knn = new KNearestNeighbors(k: 3, weighted: true);
        $knn->train($samples, $labels);

        self::assertSame('B', $knn->predict([4.5, 4.0]));
    }
}
