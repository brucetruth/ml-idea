<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\MCP;

use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Contracts\ToolSchemaInterface;

final class McpRemoteTool implements ToolInterface, ToolSchemaInterface
{
    /** @param array<string, mixed> $inputSchema */
    public function __construct(
        private readonly McpJsonRpcClient $client,
        private readonly string $toolName,
        private readonly string $toolDescription,
        private readonly array $inputSchema,
        private readonly string $risk = 'medium',
    ) {
    }

    public function name(): string
    {
        return $this->toolName;
    }

    public function description(): string
    {
        return $this->toolDescription;
    }

    public function inputSchema(): array
    {
        return $this->inputSchema;
    }

    public function examples(): array
    {
        return [];
    }

    public function riskLevel(): string
    {
        return $this->risk;
    }

    public function invoke(array $input): string
    {
        $result = $this->client->callTool($this->toolName, $input);

        return json_encode([
            'is_error' => (bool) ($result['isError'] ?? false),
            'content' => $result['content'] ?? [],
            'text' => self::extractText($result),
        ], JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $result */
    private static function extractText(array $result): string
    {
        $parts = [];
        foreach (($result['content'] ?? []) as $block) {
            if (!is_array($block)) {
                continue;
            }
            if (($block['type'] ?? '') === 'text') {
                $parts[] = (string) ($block['text'] ?? '');
            }
        }

        return trim(implode("\n", $parts));
    }
}
