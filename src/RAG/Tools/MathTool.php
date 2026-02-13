<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Tools;

use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Math\ExpressionEvaluator;

final class MathTool implements ToolInterface
{
    public function __construct(private readonly ExpressionEvaluator $evaluator = new ExpressionEvaluator())
    {
    }

    public function name(): string
    {
        return 'math';
    }

    public function description(): string
    {
        return 'Evaluates advanced numeric expressions (trig, logs, powers, constants). Input: {"expression":"..."}';
    }

    public function invoke(array $input): string
    {
        $expr = isset($input['expression']) ? (string) $input['expression'] : '';
        if (trim($expr) === '') {
            return 'MathTool: missing expression.';
        }

        try {
            $result = $this->evaluator->evaluate($expr);
            return json_encode($result, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return 'MathTool error: ' . $e->getMessage();
        }
    }
}
