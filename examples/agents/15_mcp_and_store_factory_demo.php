<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ML\IDEA\RAG\Agents\AgentStateStoreFactory;
use ML\IDEA\RAG\Agents\ToolRoutingAgent;
use ML\IDEA\RAG\LLM\HeuristicToolRoutingModel;
use ML\IDEA\RAG\MCP\McpHttpTransport;
use ML\IDEA\RAG\MCP\McpJsonRpcClient;
use ML\IDEA\RAG\MCP\McpToolProvider;
use ML\IDEA\RAG\Tools\MathTool;

$mode = $argv[1] ?? 'store';
$sessionId = $argv[2] ?? 'demo-session';
$message = $argv[3] ?? 'calculate sqrt(81)+11';

if ($mode === 'mcp') {
    $endpoint = (string) (getenv('MCP_ENDPOINT') ?: '');
    if ($endpoint === '') {
        fwrite(STDERR, "Set MCP_ENDPOINT to your MCP server JSON-RPC URL.\n");
        exit(1);
    }

    $client = new McpJsonRpcClient(new McpHttpTransport($endpoint));
    $tools = array_merge([new MathTool()], McpToolProvider::discoverTools($client));
    $agent = new ToolRoutingAgent(new HeuristicToolRoutingModel(), $tools);
    $result = $agent->chat($message);

    echo 'MCP tools loaded: ' . count($tools) . PHP_EOL;
    echo 'Answer: ' . $result['answer'] . PHP_EOL;
    exit(0);
}

$store = AgentStateStoreFactory::create([
    'driver' => (string) (getenv('AGENT_STORE_DRIVER') ?: 'auto'),
    'path' => __DIR__ . '/../artifacts/agent_state',
    'redis' => [
        'host' => (string) (getenv('REDIS_HOST') ?: '127.0.0.1'),
        'port' => (int) (getenv('REDIS_PORT') ?: 6379),
        'prefix' => 'mlidea:agent:',
    ],
]);

$agent = new ToolRoutingAgent(
    new HeuristicToolRoutingModel(),
    [new MathTool()],
    stateStore: $store,
);

$result = $agent->chatInSession($sessionId, $message);

echo 'Store driver: ' . (getenv('AGENT_STORE_DRIVER') ?: 'auto (file fallback when Redis unavailable)') . PHP_EOL;
echo 'Store class: ' . $store::class . PHP_EOL;
echo 'Session: ' . ($result['session_id'] ?? $sessionId) . PHP_EOL;
echo 'Answer: ' . $result['answer'] . PHP_EOL;
