<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ML\IDEA\Examples\AiAdmin\Support\AdminHeuristicRouter;
use ML\IDEA\Examples\AiAdmin\Support\AdminStore;
use ML\IDEA\Examples\AiAdmin\Support\AdminToolset;
use ML\IDEA\Examples\AiAdmin\Support\RefundRequest;
use ML\IDEA\RAG\Agents\AgentPolicy;
use ML\IDEA\RAG\Agents\AgentState;
use ML\IDEA\RAG\Agents\ToolExecutor;
use ML\IDEA\RAG\Agents\ToolInputValidator;
use ML\IDEA\RAG\Agents\ToolRoutingAgent;

/**
 * Customer submits refund → request queued → agent triages → approve (HITL) or deny.
 *
 *   php examples/ai-admin/run_refund_request_workflow.php
 *   php examples/ai-admin/run_refund_request_workflow.php deny-demo   # order #102 shipped → deny
 */

$store = new AdminStore();

$scenario = $argv[1] ?? 'approve';

$request = RefundRequest::fromCustomerForm(match ($scenario) {
    'deny-demo' => [
        'id' => 8843,
        'user_id' => 2,
        'order_id' => 102,
        'reason' => 'Changed my mind about the addon',
    ],
    default => [
        'id' => 8842,
        'user_id' => 2,
        'order_id' => 101,
        'reason' => 'I was charged twice for the same plan',
    ],
});

$store->submitRefundRequest($request);

echo "=== Customer refund → agent triage ===" . PHP_EOL . PHP_EOL;

echo "1) Customer submits refund form" . PHP_EOL;
echo sprintf(
    "   Request #%d | user=%d | order=%d | reason: %s\n",
    $request->id,
    $request->userId,
    $request->orderId,
    $request->reason
);
echo "   Status: pending" . PHP_EOL . PHP_EOL;

echo "2) App dispatches to agent (queue/job/API — not the customer talking to the LLM directly)" . PHP_EOL;

$agent = new ToolRoutingAgent(
    new AdminHeuristicRouter(),
    AdminToolset::make($store),
    agentName: 'RefundTriageAgent',
    systemPrompt: implode("\n", [
        'You triage customer refund requests for a SaaS app.',
        'Always verify the customer and order with read-only tools first.',
        'Approve with refund_order only when policy allows.',
        'Deny with a final answer when ineligible — do not call refund_order.',
        'Escalate when uncertain.',
    ]),
    toolExecutor: new ToolExecutor(
        new ToolInputValidator(),
        new AgentPolicy(pauseForApproval: true),
    ),
);

$sessionId = 'refund-request-' . $request->id;
$result = $agent->chat($request->toAgentMessage());

echo "   Agent session: {$sessionId}" . PHP_EOL;
echo "   First pass answer: " . $result['answer'] . PHP_EOL;
echo "   Stop reason: " . $result['stop_reason'] . PHP_EOL . PHP_EOL;

if (($result['stop_reason'] ?? '') === 'awaiting_approval') {
    echo "3) Agent decided to PROCEED — high-risk refund_order requires human approval" . PHP_EOL;
    echo '   Pending: ' . json_encode($result['pending_approval'] ?? [], JSON_THROW_ON_ERROR) . PHP_EOL;
    echo "   (Admin dashboard shows investigation + Approve/Deny buttons)" . PHP_EOL . PHP_EOL;

    echo "4) Admin approves in dashboard → resumeWithApproval()" . PHP_EOL;
    $result = $agent->resumeWithApproval(
        AgentState::fromArray($result['state']),
        true,
        (string) ($result['approval_token'] ?? ''),
    );
    $store->markRefundRequest('approved', $request->id, $result['answer']);
    echo '   Final: ' . $result['answer'] . PHP_EOL;
} else {
    echo "3) Agent decided WITHOUT calling refund_order (deny or escalate)" . PHP_EOL;
    $store->markRefundRequest(
        str_contains(strtolower($result['answer']), 'deny') ? 'denied' : 'escalated',
        $request->id,
        $result['answer'],
    );
    echo '   Decision: ' . $result['answer'] . PHP_EOL;
}

echo PHP_EOL . "Request #{$request->id} final status: " . ($store->getRefundRequest($request->id)?->status ?? 'unknown') . PHP_EOL;
echo PHP_EOL . 'See INTERACTION.md § Customer-initiated refund requests' . PHP_EOL;
