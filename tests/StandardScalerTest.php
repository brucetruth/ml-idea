<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Preprocessing\StandardScaler;
use PHPUnit\Framework\TestCase;

final class StandardScalerTest extends TestCase
{
    public function testFitTransformProducesCenteredData(): void
    {
        $samples = [[1.0, 10.0], [2.0, 20.0], [3.0, 30.0]];

        $scaler = new StandardScaler();
        $scaled = $scaler->fitTransform($samples);

        self::assertEqualsWithDelta(0.0, ($scaled[0][0] + $scaled[1][0] + $scaled[2][0]) / 3, 1.0e-9);
        self::assertEqualsWithDelta(0.0, ($scaled[0][1] + $scaled[1][1] + $scaled[2][1]) / 3, 1.0e-9);
    }
}
