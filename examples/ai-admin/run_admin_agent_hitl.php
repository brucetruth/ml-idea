<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ML\IDEA\Examples\AiAdmin\Support\AdminHeuristicRouter;
use ML\IDEA\Examples\AiAdmin\Support\AdminToolset;
use ML\IDEA\RAG\Agents\AgentPolicy;
use ML\IDEA\RAG\Agents\AgentState;
use ML\IDEA\RAG\Agents\ToolExecutor;
use ML\IDEA\RAG\Agents\ToolInputValidator;
use ML\IDEA\RAG\Agents\ToolRoutingAgent;

$query = $argv[1] ?? 'Ban user #2 for chargeback abuse';

$agent = new ToolRoutingAgent(
    new AdminHeuristicRouter(),
    AdminToolset::make(),
    agentName: 'AiAdmin',
    toolExecutor: new ToolExecutor(
        new ToolInputValidator(),
        new AgentPolicy(pauseForApproval: true),
    ),
    systemPrompt: 'You are an AI admin assistant. High-risk tools require human approval.',
);

$paused = $agent->chat($query);

echo 'Query: ' . $query . PHP_EOL;
echo 'Stop reason: ' . $paused['stop_reason'] . PHP_EOL;

if (($paused['stop_reason'] ?? '') !== 'awaiting_approval') {
    echo 'Answer: ' . ($paused['answer'] ?? '') . PHP_EOL;
    exit(0);
}

echo 'Pending approval for: ' . ($paused['pending_approval']['tool'] ?? 'unknown') . PHP_EOL;
echo 'Approval token: ' . ($paused['approval_token'] ?? '') . PHP_EOL;

$approved = !in_array(strtolower((string) ($argv[2] ?? 'yes')), ['no', 'deny', '0', 'false'], true);
$resumed = $agent->resumeWithApproval(
    AgentState::fromArray($paused['state']),
    $approved,
    (string) ($paused['approval_token'] ?? ''),
);

echo 'Approved: ' . ($approved ? 'yes' : 'no') . PHP_EOL;
echo 'Final answer: ' . ($resumed['answer'] ?? '') . PHP_EOL;
