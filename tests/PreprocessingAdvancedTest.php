<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Preprocessing\OneHotEncoder;
use ML\IDEA\Preprocessing\PolynomialFeatures;
use ML\IDEA\Preprocessing\SimpleImputer;
use PHPUnit\Framework\TestCase;

final class PreprocessingAdvancedTest extends TestCase
{
    public function testOneHotEncoderProducesExpandedVectors(): void
    {
        $samples = [
            ['red', 'S'],
            ['blue', 'M'],
            ['red', 'M'],
        ];

        $enc = new OneHotEncoder();
        $encoded = $enc->fitTransform($samples);

        self::assertCount(3, $encoded);
        self::assertGreaterThan(2, count($encoded[0]));
    }

    public function testSimpleImputerFillsNaNs(): void
    {
        $samples = [
            [1.0, NAN],
            [2.0, 4.0],
            [3.0, 6.0],
        ];

        $imputer = new SimpleImputer('mean');
        $filled = $imputer->fitTransform($samples);

        self::assertFalse(is_nan($filled[0][1]));
    }

    public function testPolynomialFeaturesAddsQuadraticTerms(): void
    {
        $samples = [[2.0, 3.0]];
        $poly = new PolynomialFeatures(2);
        $transformed = $poly->fitTransform($samples);

        self::assertGreaterThan(2, count($transformed[0]));
    }
}
