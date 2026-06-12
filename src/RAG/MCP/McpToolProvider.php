<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\MCP;

use ML\IDEA\RAG\Contracts\ToolInterface;

final class McpToolProvider
{
    /** @return array<int, ToolInterface> */
    public static function discoverTools(McpJsonRpcClient $client, string $defaultRiskLevel = 'medium'): array
    {
        $tools = [];
        foreach ($client->listTools() as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $name = isset($definition['name']) ? (string) $definition['name'] : '';
            if ($name === '') {
                continue;
            }

            $description = isset($definition['description']) ? (string) $definition['description'] : ('MCP tool ' . $name);
            /** @var array<string, mixed> $schema */
            $schema = isset($definition['inputSchema']) && is_array($definition['inputSchema'])
                ? $definition['inputSchema']
                : (isset($definition['input_schema']) && is_array($definition['input_schema']) ? $definition['input_schema'] : ['type' => 'object', 'properties' => new \stdClass()]);

            $tools[] = new McpRemoteTool($client, $name, $description, $schema, $defaultRiskLevel);
        }

        return $tools;
    }
}
