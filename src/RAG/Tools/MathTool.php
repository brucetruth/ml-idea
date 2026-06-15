<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Tools;

use ML\IDEA\RAG\Contracts\ParallelInvokableToolInterface;
use ML\IDEA\RAG\Contracts\RetryableToolInterface;
use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Contracts\ToolSchemaInterface;
use ML\IDEA\RAG\Math\ExpressionEvaluator;

final class MathTool implements ToolInterface, ToolSchemaInterface, RetryableToolInterface, ParallelInvokableToolInterface
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

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['expression'],
            'properties' => [
                'expression' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 500],
            ],
        ];
    }

    public function examples(): array
    {
        return [
            ['expression' => 'sqrt(81)+11'],
            ['expression' => 'sin(pi/2)'],
        ];
    }

    public function riskLevel(): string
    {
        return 'low';
    }

    public function isRetryable(): bool
    {
        return true;
    }

    public function invoke(array $input): string
    {
        return self::invokeParallel($input);
    }

    /** @param array<string, mixed> $input */
    public static function invokeParallel(array $input): string
    {
        $expr = isset($input['expression']) ? (string) $input['expression'] : '';
        if (trim($expr) === '') {
            return 'MathTool: missing expression.';
        }

        try {
            $result = (new ExpressionEvaluator())->evaluate($expr);

            return json_encode($result, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return 'MathTool error: ' . $e->getMessage();
        }
    }
}
