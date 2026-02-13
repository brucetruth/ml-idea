<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\RAG\Math\ExpressionEvaluator;
use ML\IDEA\RAG\Tools\MathTool;
use PHPUnit\Framework\TestCase;

final class RagMathToolTest extends TestCase
{
    public function testExpressionEvaluatorHandlesComplexMath(): void
    {
        $eval = new ExpressionEvaluator();

        $result = $eval->evaluate('sqrt(144) + pow(2,3) + sin(pi/2)');
        self::assertEqualsWithDelta(21.0, $result['result'], 1e-9);
    }

    public function testMathToolReturnsJsonResult(): void
    {
        $tool = new MathTool();
        $output = $tool->invoke(['expression' => '3^2 + 4^2']);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(25.0, (float) $decoded['result']);
    }
}
