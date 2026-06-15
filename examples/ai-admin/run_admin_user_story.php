<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ML\IDEA\Examples\AiAdmin\Support\AdminHeuristicRouter;
use ML\IDEA\Examples\AiAdmin\Support\AdminToolset;
use ML\IDEA\RAG\Agents\AgentPolicy;
use ML\IDEA\RAG\Agents\AgentState;
use ML\IDEA\RAG\Agents\AgentStateStoreFactory;
use ML\IDEA\RAG\Agents\ToolExecutor;
use ML\IDEA\RAG\Agents\ToolInputValidator;
use ML\IDEA\RAG\Agents\ToolRoutingAgent;

/**
 * Multi-turn AI admin demo: you send user stories; the agent picks tools each turn.
 *
 * Run:
 *   php examples/ai-admin/run_admin_user_story.php
 *
 * With OpenAI (real tool routing from natural language):
 *   MLIDEA_USE_OPENAI=1 OPENAI_API_KEY=sk-... php examples/ai-admin/run_admin_user_story.php
 */

$store = AgentStateStoreFactory::create([
    'driver' => 'file',
    'path' => sys_get_temp_dir() . '/mlidea_ai_admin_story',
]);

$sessionId = 'billing-complaint-' . date('YmdHis');

$model = (getenv('MLIDEA_USE_OPENAI') === '1' && getenv('OPENAI_API_KEY'))
    ? new \ML\IDEA\RAG\LLM\OpenAIToolRoutingModel((string) getenv('OPENAI_API_KEY'))
    : new AdminHeuristicRouter();

$agent = new ToolRoutingAgent(
    $model,
    AdminToolset::make(),
    agentName: 'AiAdmin',
    systemPrompt: implode("\n", [
        'You are an AI admin assistant for a SaaS billing and user management panel.',
        'When an admin describes a customer issue, investigate with read-only tools first.',
        'Then recommend or perform fixes (refunds, bans, role changes) when asked.',
        'Always explain what you checked and why you chose an action.',
    ]),
    toolExecutor: new ToolExecutor(
        new ToolInputValidator(),
        new AgentPolicy(pauseForApproval: true),
    ),
    stateStore: $store,
);

/** @var array<int, array{label: string, message: string}> $turns */
$turns = [
    [
        'label' => 'Turn 1 — admin describes the situation',
        'message' => 'Customer Bob (user #2) emailed that order #101 might be a duplicate charge. '
            . 'Please check his account and orders and summarize what you find.',
    ],
    [
        'label' => 'Turn 2 — admin asks for action',
        'message' => 'Refund order #101. Reason: duplicate charge, support ticket #8842.',
    ],
    [
        'label' => 'Turn 3 — follow-up in same session',
        'message' => 'What is user #2 account status now after that refund?',
    ],
];

echo "=== AI Admin user story demo ===" . PHP_EOL;
echo 'Session: ' . $sessionId . PHP_EOL;
echo 'Model: ' . ($model instanceof AdminHeuristicRouter ? 'AdminHeuristicRouter (offline)' : 'OpenAIToolRoutingModel') . PHP_EOL;
echo 'Docs: examples/ai-admin/INTERACTION.md' . PHP_EOL . PHP_EOL;

$decisionOffset = 0;

foreach ($turns as $index => $turn) {
    echo str_repeat('─', 72) . PHP_EOL;
    echo $turn['label'] . PHP_EOL;
    echo 'Admin says: ' . $turn['message'] . PHP_EOL . PHP_EOL;

    $result = $agent->chatInSession($sessionId, $turn['message']);
    $turnDecisions = array_slice($result['decisions'] ?? [], $decisionOffset);
    $decisionOffset = count($result['decisions'] ?? []);

    echo 'Agent answer: ' . $result['answer'] . PHP_EOL;
    echo 'Stop reason: ' . $result['stop_reason'] . PHP_EOL;

    $toolLines = [];
    foreach ($turnDecisions as $decision) {
        if (($decision['type'] ?? '') !== 'tool_call' && ($decision['type'] ?? '') !== 'tool_calls') {
            continue;
        }
        foreach ($decision['tool_calls'] ?? [] as $call) {
            $toolLines[] = sprintf(
                '  - %s %s',
                $call['tool'] ?? '?',
                json_encode($call['input'] ?? [], JSON_THROW_ON_ERROR)
            );
        }
    }
    echo $toolLines === [] ? "Tools used this turn: (none)\n" : "Tools used this turn:\n" . implode(PHP_EOL, $toolLines) . PHP_EOL;

    if (($result['stop_reason'] ?? '') === 'awaiting_approval') {
        echo PHP_EOL . '→ Paused for human approval (refund/ban). Auto-approving for demo…' . PHP_EOL;
        $result = $agent->resumeWithApproval(
            AgentState::fromArray($result['state']),
            true,
            (string) ($result['approval_token'] ?? ''),
        );
        $decisionOffset = count($result['decisions'] ?? []);
        echo 'After approval: ' . $result['answer'] . PHP_EOL;
        echo 'Stop reason: ' . $result['stop_reason'] . PHP_EOL;
    }

    if ($turnDecisions !== []) {
        $types = array_map(static fn (array $d): string => (string) ($d['type'] ?? '?'), $turnDecisions);
        echo 'Decision chain this turn: ' . implode(' → ', $types) . PHP_EOL;
    }

    echo PHP_EOL;
}

echo str_repeat('─', 72) . PHP_EOL;
echo 'Done. Same session_id keeps conversation + tool results for the next message.' . PHP_EOL;
echo 'In Laravel: POST /api/admin/ai/chat with message + session_id (see INTERACTION.md).' . PHP_EOL;
