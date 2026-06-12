<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

final class AgentDecision
{
    /**
     * @param array<int, array{tool: string, input: array<string, mixed>, provider_call_id?: string}> $toolCalls
     * @param array<string, mixed> $raw
     */
    private function __construct(
        public readonly string $type,
        public readonly string $content = '',
        public readonly array $toolCalls = [],
        public readonly array $raw = [],
        public readonly ?float $confidence = null,
        public readonly ?string $handoffTarget = null,
    ) {
    }

    /** @param array<string, mixed> $raw */
    public static function fromArray(array $raw): self
    {
        $type = isset($raw['type']) ? (string) $raw['type'] : 'final';
        if (!in_array($type, ['final', 'tool_call', 'tool_calls', 'clarify', 'refuse', 'plan', 'handoff'], true)) {
            return new self('final', 'Invalid decision type; provide final answer.', [], $raw);
        }

        if ($type === 'tool_call' || $type === 'tool_calls') {
            return new self($type, '', self::extractToolCalls($raw), $raw, self::extractConfidence($raw));
        }

        if ($type === 'handoff') {
            return new self(
                'handoff',
                self::extractHandoffTask($raw),
                [],
                $raw,
                self::extractConfidence($raw),
                self::extractHandoffTarget($raw),
            );
        }

        return new self($type, isset($raw['content']) ? (string) $raw['content'] : '', [], $raw, self::extractConfidence($raw));
    }

    /** @param array<string, mixed> $raw */
    private static function extractHandoffTarget(array $raw): ?string
    {
        foreach (['agent', 'target', 'handoff_to', 'specialist'] as $key) {
            if (isset($raw[$key]) && trim((string) $raw[$key]) !== '') {
                return trim((string) $raw[$key]);
            }
        }

        return null;
    }

    /** @param array<string, mixed> $raw */
    private static function extractHandoffTask(array $raw): string
    {
        foreach (['content', 'task', 'message'] as $key) {
            if (isset($raw[$key]) && trim((string) $raw[$key]) !== '') {
                return trim((string) $raw[$key]);
            }
        }

        return '';
    }

    /** @param array<string, mixed> $raw */
    private static function extractConfidence(array $raw): ?float
    {
        if (!isset($raw['confidence'])) {
            return null;
        }

        $confidence = (float) $raw['confidence'];
        if ($confidence < 0.0) {
            return 0.0;
        }
        if ($confidence > 1.0) {
            return 1.0;
        }

        return $confidence;
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<int, array{tool: string, input: array<string, mixed>, provider_call_id?: string}>
     */
    private static function extractToolCalls(array $raw): array
    {
        if (($raw['type'] ?? '') === 'tool_calls' && isset($raw['tool_calls']) && is_array($raw['tool_calls'])) {
            $calls = [];
            foreach ($raw['tool_calls'] as $call) {
                if (!is_array($call)) {
                    continue;
                }
                $tool = isset($call['tool']) ? (string) $call['tool'] : (isset($call['name']) ? (string) $call['name'] : '');
                /** @var array<string, mixed> $input */
                $input = isset($call['input']) && is_array($call['input']) ? $call['input'] : [];
                if ($tool !== '') {
                    $normalized = ['tool' => $tool, 'input' => $input];
                    if (isset($call['provider_call_id'])) {
                        $normalized['provider_call_id'] = (string) $call['provider_call_id'];
                    }
                    $calls[] = $normalized;
                }
            }
            return $calls;
        }

        $tool = isset($raw['tool']) ? (string) $raw['tool'] : '';
        /** @var array<string, mixed> $input */
        $input = isset($raw['input']) && is_array($raw['input']) ? $raw['input'] : [];
        if ($tool === '') {
            return [];
        }

        $call = ['tool' => $tool, 'input' => $input];
        if (isset($raw['provider_call_id'])) {
            $call['provider_call_id'] = (string) $raw['provider_call_id'];
        }

        return [$call];
    }

    public function isTerminal(): bool
    {
        return in_array($this->type, ['final', 'clarify', 'refuse'], true);
    }
}
