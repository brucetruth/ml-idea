<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Classifiers\KNearestNeighbors;
use ML\IDEA\Pipeline\PipelineClassifier;
use ML\IDEA\Preprocessing\StandardScaler;
use PHPUnit\Framework\TestCase;

final class PipelineClassifierTest extends TestCase
{
    public function testPipelineCanTrainAndPredict(): void
    {
        $samples = [[1, 1], [1, 2], [2, 1], [4, 4], [5, 5], [4, 5]];
        $labels = ['A', 'A', 'A', 'B', 'B', 'B'];

        $pipeline = new PipelineClassifier(
            [new StandardScaler()],
            new KNearestNeighbors(k: 3, weighted: true)
        );

        $pipeline->train($samples, $labels);

        self::assertSame('B', $pipeline->predict([4.8, 4.6]));
    }
}
