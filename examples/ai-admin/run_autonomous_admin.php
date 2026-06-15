<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ML\IDEA\Examples\AiAdmin\Support\AdminHeuristicRouter;
use ML\IDEA\Examples\AiAdmin\Support\AdminStore;
use ML\IDEA\Examples\AiAdmin\Support\AdminToolset;
use ML\IDEA\RAG\Agents\AgentPolicy;
use ML\IDEA\RAG\Agents\ToolExecutor;
use ML\IDEA\RAG\Agents\ToolInputValidator;
use ML\IDEA\RAG\Agents\ToolRoutingAgent;

/**
 * Full AI admin for low/medium-risk actions — agent writes to the store with no human step.
 *
 * High-risk tools (ban_user, refund_order) are excluded from the toolset.
 * pauseForApproval=true only pauses on `high` risk — low/medium tools run immediately.
 *
 * Usage:
 *   php examples/ai-admin/run_autonomous_admin.php
 */
$store = new AdminStore();

$prompt = $argv[1] ?? implode("\n", [
    'AUTONOMOUS SUPPORT TICKET #501',
    '- user_id=2',
    '- order_id=101',
    '- subject: Invoice looks wrong',
    '',
    'Triage this ticket without human approval:',
    '1) Look up the customer and their orders.',
    '2) Add an internal note that billing review was opened.',
    '3) Tag order #101 as billing-review.',
    '4) Set ticket #501 status to pending.',
    '5) Summarize what you did.',
]);

$agent = new ToolRoutingAgent(
    new AdminHeuristicRouter(),
    AdminToolset::makeAutonomous($store),
    agentName: 'AiAdminAutonomous',
    toolExecutor: new ToolExecutor(
        new ToolInputValidator(),
        new AgentPolicy(pauseForApproval: true),
    ),
    systemPrompt: implode("\n", [
        'You are an autonomous AI admin for routine support work.',
        'Use read tools first, then low-risk write tools (add_user_note, tag_order, update_support_ticket_status).',
        'Never attempt refunds or bans — those require a human.',
    ]),
);

$result = $agent->chat($prompt);

echo 'Prompt:' . PHP_EOL . $prompt . PHP_EOL . PHP_EOL;
echo 'Answer: ' . ($result['answer'] ?? '') . PHP_EOL;
echo 'Stop reason: ' . ($result['stop_reason'] ?? '') . PHP_EOL;
echo 'Tool calls: ' . count($result['tool_calls'] ?? []) . PHP_EOL;

if (($result['stop_reason'] ?? '') === 'awaiting_approval') {
    echo PHP_EOL . 'Unexpected: autonomous flow should not pause for approval.' . PHP_EOL;
    exit(1);
}

echo PHP_EOL . '--- Store after agent run (DB writes applied) ---' . PHP_EOL;
echo 'User #2 notes: ' . json_encode($store->userNotes(2), JSON_THROW_ON_ERROR) . PHP_EOL;
echo 'Order #101 tags: ' . json_encode($store->orderTags(101), JSON_THROW_ON_ERROR) . PHP_EOL;
$ticket = $store->getSupportTicket(501);
echo 'Ticket #501: ' . json_encode($ticket, JSON_THROW_ON_ERROR) . PHP_EOL;
