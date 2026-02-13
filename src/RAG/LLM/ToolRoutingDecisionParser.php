<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\LLM;

final class ToolRoutingDecisionParser
{
    /**
     * @return array{type: string, content?: string, tool?: string, input?: array<string, mixed>}
     */
    public static function parse(string $raw): array
    {
        $clean = trim($raw);
        $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean) ?? $clean;
        $clean = preg_replace('/\s*```$/', '', $clean) ?? $clean;

        $decoded = json_decode($clean, true);
        if (!is_array($decoded)) {
            return ['type' => 'final', 'content' => $raw];
        }

        $type = isset($decoded['type']) ? (string) $decoded['type'] : 'final';
        if ($type !== 'tool_call' && $type !== 'final') {
            $type = 'final';
        }

        if ($type === 'tool_call') {
            $tool = isset($decoded['tool']) ? (string) $decoded['tool'] : '';
            $input = isset($decoded['input']) && is_array($decoded['input']) ? $decoded['input'] : [];
            if ($tool === '') {
                return ['type' => 'final', 'content' => $raw];
            }

            return ['type' => 'tool_call', 'tool' => $tool, 'input' => $input];
        }

        return ['type' => 'final', 'content' => isset($decoded['content']) ? (string) $decoded['content'] : $raw];
    }
}
