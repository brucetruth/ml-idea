<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

use ML\IDEA\RAG\Contracts\ToolInterface;

final class ToolCallingAgent
{
    /** @var array<string, ToolInterface> */
    private array $tools = [];

    /** @param array<int, ToolInterface> $tools */
    public function __construct(
        array $tools,
        private readonly string $agentName = 'ToolCallingAgent',
        /** @var array<int, string> */
        private readonly array $agentFeatures = [],
        private readonly ?string $systemPrompt = null,
    )
    {
        foreach ($tools as $tool) {
            $this->tools[$tool->name()] = $tool;
        }
    }

    public function getSystemPrompt(): string
    {
        if (is_string($this->systemPrompt) && trim($this->systemPrompt) !== '') {
            return trim($this->systemPrompt);
        }

        $name = trim($this->agentName);
        $features = array_values(array_filter(array_map(
            static fn (mixed $f): string => trim((string) $f),
            $this->agentFeatures
        ), static fn (string $f): bool => $f !== ''));

        $lines = [
            sprintf('You are %s.', $name !== '' ? $name : 'ToolCallingAgent'),
            'You execute explicitly requested tool calls using the protocol: tool:TOOL_NAME {"key":"value"}.',
        ];

        if ($features !== []) {
            $lines[] = 'Agent features:';
            foreach ($features as $feature) {
                $lines[] = '- ' . $feature;
            }
        }

        return implode("\n", $lines);
    }

    public function getInvocationGuide(): string
    {
        return 'No tool invocation detected. Use: tool:TOOL_NAME {"arg":"value"}';
    }

    /**
     * Minimal protocol:
     * tool:TOOL_NAME {"key":"value"}
     */
    public function run(string $instruction): string
    {
        if (!preg_match('/^tool:([a-zA-Z0-9_\-]+)\s*(\{.*\})?$/', trim($instruction), $m)) {
            return $this->getInvocationGuide();
        }

        $name = $m[1];
        $payload = $m[2] ?? '{}';

        if (!isset($this->tools[$name])) {
            return sprintf('Unknown tool: %s', $name);
        }

        /** @var array<string, mixed> $input */
        $input = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        return $this->tools[$name]->invoke($input);
    }
}
