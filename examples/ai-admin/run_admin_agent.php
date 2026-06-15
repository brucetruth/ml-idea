<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ML\IDEA\Examples\AiAdmin\Support\AdminHeuristicRouter;
use ML\IDEA\Examples\AiAdmin\Support\AdminToolset;
use ML\IDEA\RAG\Agents\ToolRoutingAgent;

$query = $argv[1] ?? 'List all users in the app';

$agent = new ToolRoutingAgent(
    new AdminHeuristicRouter(),
    AdminToolset::make(),
    agentName: 'AiAdmin',
    agentFeatures: [
        'operates on users and orders',
        'uses admin tools for read and write actions',
    ],
    systemPrompt: implode("\n", [
        'You are an AI admin assistant for a SaaS application.',
        'Use tools to inspect users/orders and perform admin actions.',
        'Prefer read-only tools before mutating data.',
        'High-risk actions (ban_user, refund_order) should be explained clearly.',
    ]),
);

$result = $agent->chat($query);

echo 'Query: ' . $query . PHP_EOL;
echo 'Answer: ' . $result['answer'] . PHP_EOL;
echo 'Stop reason: ' . $result['stop_reason'] . PHP_EOL;
echo 'Tool calls: ' . count($result['tool_calls']) . PHP_EOL;
