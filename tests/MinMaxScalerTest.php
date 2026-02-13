<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Preprocessing\MinMaxScaler;
use PHPUnit\Framework\TestCase;

final class MinMaxScalerTest extends TestCase
{
    public function testFitTransformScalesToUnitRange(): void
    {
        $samples = [[1.0, 10.0], [2.0, 20.0], [3.0, 30.0]];

        $scaler = new MinMaxScaler();
        $scaled = $scaler->fitTransform($samples);

        self::assertSame([0.0, 0.0], $scaled[0]);
        self::assertSame([0.5, 0.5], $scaled[1]);
        self::assertSame([1.0, 1.0], $scaled[2]);
    }
}
