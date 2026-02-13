<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Classifiers\KNearestNeighbors;
use ML\IDEA\ModelSelection\GridSearchClassifier;
use PHPUnit\Framework\TestCase;

final class GridSearchClassifierTest extends TestCase
{
    public function testFindsBestParamsAndEstimator(): void
    {
        $samples = [[1, 1], [1, 2], [2, 1], [4, 4], [5, 5], [4, 5]];
        $labels = ['A', 'A', 'A', 'B', 'B', 'B'];

        $search = new GridSearchClassifier(
            static fn (array $p): KNearestNeighbors => new KNearestNeighbors((int) $p['k'], (bool) $p['weighted']),
            scoring: 'accuracy',
            cv: 3,
        );

        $search->fit($samples, $labels, [
            'k' => [1, 3],
            'weighted' => [true, false],
        ]);

        self::assertGreaterThanOrEqual(0.0, $search->bestScore());
        self::assertNotEmpty($search->bestParams());
        self::assertSame('B', $search->bestEstimator()->predict([4.7, 4.6]));
    }
}
