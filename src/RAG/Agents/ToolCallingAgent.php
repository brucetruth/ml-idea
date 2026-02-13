<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

use ML\IDEA\RAG\Contracts\ToolInterface;

final class ToolCallingAgent
{
    /** @var array<string, ToolInterface> */
    private array $tools = [];

    /** @param array<int, ToolInterface> $tools */
    public function __construct(array $tools)
    {
        foreach ($tools as $tool) {
            $this->tools[$tool->name()] = $tool;
        }
    }

    /**
     * Minimal protocol:
     * tool:TOOL_NAME {"key":"value"}
     */
    public function run(string $instruction): string
    {
        if (!preg_match('/^tool:([a-zA-Z0-9_\-]+)\s*(\{.*\})?$/', trim($instruction), $m)) {
            return 'No tool invocation detected. Use: tool:TOOL_NAME {"arg":"value"}';
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
