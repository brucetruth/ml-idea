<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

use ML\IDEA\RAG\Contracts\ToolSchemaInterface;

final class ToolCallBatchPlanner
{
    private const RISK_ORDER = ['low' => 0, 'medium' => 1, 'high' => 2];

    /**
     * Order tool calls low → medium → high so reads/investigation run before mutating actions.
     *
     * @param array<int, array{tool: string, input: array<string, mixed>, provider_call_id?: string}> $calls
     * @param array<string, \ML\IDEA\RAG\Contracts\ToolInterface> $tools
     * @return array<int, array{tool: string, input: array<string, mixed>, provider_call_id?: string}>
     */
    public static function orderByRisk(array $calls, array $tools): array
    {
        if (count($calls) <= 1) {
            return $calls;
        }

        usort($calls, static function (array $a, array $b) use ($tools): int {
            $rankA = self::riskRank($a, $tools);
            $rankB = self::riskRank($b, $tools);

            return $rankA <=> $rankB;
        });

        return $calls;
    }

    /**
     * @param array{tool: string, input: array<string, mixed>} $call
     * @param array<string, \ML\IDEA\RAG\Contracts\ToolInterface> $tools
     */
    private static function riskRank(array $call, array $tools): int
    {
        $toolName = (string) ($call['tool'] ?? '');
        $tool = $tools[$toolName] ?? null;
        $level = $tool instanceof ToolSchemaInterface ? $tool->riskLevel() : 'medium';

        return self::RISK_ORDER[$level] ?? 1;
    }
}
