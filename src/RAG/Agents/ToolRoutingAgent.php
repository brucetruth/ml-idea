<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Contracts\ToolRoutingModelInterface;

final class ToolRoutingAgent
{
    /** @var array<string, ToolInterface> */
    private array $tools = [];

    /** @param array<int, ToolInterface> $tools */
    public function __construct(
        private readonly ToolRoutingModelInterface $model,
        array $tools,
        private readonly int $maxIterations = 8,
        private readonly string $agentName = 'ToolRoutingAgent',
        /** @var array<int, string> */
        private readonly array $agentFeatures = [],
        private readonly ?string $systemPrompt = null,
    ) {
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

        if ($name === 'ToolRoutingAgent' && $features === []) {
            return 'You are a tool-using agent. Decide whether to call a tool or answer directly.';
        }

        $lines = [
            sprintf('You are %s, a tool-using agent.', $name !== '' ? $name : 'ToolRoutingAgent'),
            'Decide whether to call a tool or answer directly.',
        ];

        if ($features !== []) {
            $lines[] = 'Agent features:';
            foreach ($features as $feature) {
                $lines[] = '- ' . $feature;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{answer: string, iterations: int, tool_calls: array<int, array{name: string, input: array<string, mixed>, output: string}>, trace: array<int, array{role: string, content: string}>}
     */
    public function chat(string $userMessage): array
    {
        $messages = [
            ['role' => 'system', 'content' => $this->getSystemPrompt()],
            ['role' => 'user', 'content' => $userMessage],
        ];

        $toolSchemas = [];
        foreach ($this->tools as $tool) {
            $toolSchemas[] = ['name' => $tool->name(), 'description' => $tool->description()];
        }

        $calls = [];
        for ($i = 0; $i < $this->maxIterations; $i++) {
            $decision = $this->model->respond($messages, $toolSchemas);
            $type = $decision['type'];

            if ($type === 'final') {
                $answer = isset($decision['content']) ? (string) $decision['content'] : 'No response content.';
                return [
                    'answer' => $answer,
                    'iterations' => $i + 1,
                    'tool_calls' => $calls,
                    'trace' => $messages,
                ];
            }

            if ($type !== 'tool_call') {
                $messages[] = ['role' => 'assistant', 'content' => 'Invalid decision type; provide final answer.'];
                continue;
            }

            $toolName = isset($decision['tool']) ? (string) $decision['tool'] : '';
            /** @var array<string, mixed> $toolInput */
            $toolInput = $decision['input'] ?? [];

            if (!isset($this->tools[$toolName])) {
                $output = sprintf('Tool not found: %s', $toolName);
            } else {
                $output = $this->tools[$toolName]->invoke($toolInput);
            }

            $calls[] = ['name' => $toolName, 'input' => $toolInput, 'output' => $output];
            $messages[] = ['role' => 'assistant', 'content' => sprintf('TOOL_CALL %s %s', $toolName, json_encode($toolInput, JSON_THROW_ON_ERROR))];
            $messages[] = ['role' => 'tool', 'content' => $output];
        }

        return [
            'answer' => 'Max iterations reached without final answer.',
            'iterations' => $this->maxIterations,
            'tool_calls' => $calls,
            'trace' => $messages,
        ];
    }
}
