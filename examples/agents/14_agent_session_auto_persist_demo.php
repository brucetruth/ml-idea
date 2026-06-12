<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ML\IDEA\RAG\Agents\AgentStateStoreFactory;
use ML\IDEA\RAG\Agents\ToolRoutingAgent;
use ML\IDEA\RAG\LLM\HeuristicToolRoutingModel;
use ML\IDEA\RAG\Tools\MathTool;

$store = AgentStateStoreFactory::create([
    'driver' => 'file',
    'path' => __DIR__ . '/../artifacts/agent_state',
]);
$sessionId = $argv[1] ?? 'auto-session';
$message = $argv[2] ?? 'calculate sqrt(81)+11';

$agent = new ToolRoutingAgent(
    new HeuristicToolRoutingModel(),
    [new MathTool()],
    stateStore: $store,
);

$result = $agent->chatInSession($sessionId, $message);

echo 'Session: ' . ($result['session_id'] ?? $sessionId) . PHP_EOL;
echo 'Answer: ' . $result['answer'] . PHP_EOL;
echo 'Stop reason: ' . $result['stop_reason'] . PHP_EOL;
echo 'Messages in trace: ' . count($result['trace']) . PHP_EOL;
echo 'State auto-saved under examples/artifacts/agent_state' . PHP_EOL;
