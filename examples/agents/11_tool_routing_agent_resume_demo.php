<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ML\IDEA\RAG\Agents\AgentBudget;
use ML\IDEA\RAG\Agents\JsonFileAgentStateStore;
use ML\IDEA\RAG\Agents\ToolRoutingAgent;
use ML\IDEA\RAG\LLM\HeuristicToolRoutingModel;
use ML\IDEA\RAG\Tools\MathTool;

$store = new JsonFileAgentStateStore(__DIR__ . '/../artifacts/agent_state');
$sessionId = $argv[1] ?? 'demo-session';
$message = $argv[2] ?? 'calculate sqrt(81)+11';

$agent = new ToolRoutingAgent(
    new HeuristicToolRoutingModel(),
    [new MathTool()],
    budget: new AgentBudget(maxIterations: 4, maxToolCalls: 4)
);

$state = $store->load($sessionId);
$result = $state === null
    ? $agent->chat($message)
    : $agent->chatWithState($state);

$store->save($sessionId, \ML\IDEA\RAG\Agents\AgentState::fromArray($result['state']));

echo 'Session: ' . $sessionId . PHP_EOL;
echo 'Answer: ' . $result['answer'] . PHP_EOL;
echo 'Stop reason: ' . $result['stop_reason'] . PHP_EOL;
echo 'Tool calls: ' . count($result['tool_calls']) . PHP_EOL;
echo 'State saved under examples/artifacts/agent_state' . PHP_EOL;

