<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\LLM;

final class ToolRoutingDecisionParser
{
    /**
     * @return array<string, mixed>
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
        if (!in_array($type, ['tool_call', 'tool_calls', 'final', 'clarify', 'refuse'], true)) {
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

        if ($type === 'tool_calls') {
            $calls = isset($decoded['tool_calls']) && is_array($decoded['tool_calls']) ? $decoded['tool_calls'] : [];
            return ['type' => 'tool_calls', 'tool_calls' => $calls];
        }

        if ($type === 'clarify' || $type === 'refuse') {
            return ['type' => $type, 'content' => isset($decoded['content']) ? (string) $decoded['content'] : $raw];
        }

        return ['type' => 'final', 'content' => isset($decoded['content']) ? (string) $decoded['content'] : $raw];
    }
}
