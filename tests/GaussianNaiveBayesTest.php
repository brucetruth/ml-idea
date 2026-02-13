<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Classifiers\GaussianNaiveBayes;
use PHPUnit\Framework\TestCase;

final class GaussianNaiveBayesTest extends TestCase
{
    public function testPredictsClassFromGaussianDistributions(): void
    {
        $samples = [
            [1.0, 20.0],
            [1.2, 19.5],
            [3.5, 5.0],
            [3.7, 4.8],
        ];
        $labels = ['cold', 'cold', 'hot', 'hot'];

        $model = new GaussianNaiveBayes();
        $model->train($samples, $labels);

        self::assertSame('hot', $model->predict([3.6, 5.1]));
        self::assertSame('cold', $model->predict([1.1, 19.8]));
    }
}
