<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ML\IDEA\RAG\Agents\AgentPolicy;
use ML\IDEA\RAG\Agents\AgentState;
use ML\IDEA\RAG\Agents\ToolExecutor;
use ML\IDEA\RAG\Agents\ToolInputValidator;
use ML\IDEA\RAG\Agents\ToolRoutingAgent;
use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Contracts\ToolRoutingModelInterface;
use ML\IDEA\RAG\Contracts\ToolSchemaInterface;
use ML\IDEA\RAG\LLM\HeuristicToolRoutingModel;
use ML\IDEA\RAG\Tools\MathTool;

$mode = $argv[1] ?? 'stream';

if ($mode === 'hitl') {
    $model = new class () implements ToolRoutingModelInterface {
        private int $turn = 0;

        public function respond(array $messages, array $tools): array
        {
            $this->turn++;
            return $this->turn === 1
                ? ['type' => 'tool_call', 'tool' => 'risky', 'input' => ['payload' => 'demo']]
                : ['type' => 'final', 'content' => 'Completed after approval.'];
        }
    };

    $tool = new class () implements ToolInterface, ToolSchemaInterface {
        public function name(): string { return 'risky'; }
        public function description(): string { return 'Demo high-risk tool.'; }
        public function invoke(array $input): string { return json_encode(['status' => 'executed', 'input' => $input], JSON_THROW_ON_ERROR); }
        public function inputSchema(): array { return ['type' => 'object', 'required' => ['payload'], 'properties' => ['payload' => ['type' => 'string']]]; }
        public function examples(): array { return [['payload' => 'demo']]; }
        public function riskLevel(): string { return 'high'; }
    };

    $policy = new AgentPolicy(pauseForApproval: true);
    $agent = new ToolRoutingAgent($model, [$tool], toolExecutor: new ToolExecutor(new ToolInputValidator(), $policy));
    $paused = $agent->chat('execute risky action');

    echo 'Paused for approval' . PHP_EOL;
    echo 'Stop reason: ' . $paused['stop_reason'] . PHP_EOL;
    echo 'Approval token: ' . ($paused['approval_token'] ?? '') . PHP_EOL;

    $approved = ($argv[2] ?? 'yes') !== 'no';
    $result = $agent->resumeWithApproval(AgentState::fromArray($paused['state']), $approved, (string) ($paused['approval_token'] ?? ''));
    echo 'Resume approved=' . ($approved ? 'yes' : 'no') . PHP_EOL;
    echo 'Answer: ' . $result['answer'] . PHP_EOL;
    exit(0);
}

$agent = new ToolRoutingAgent(new HeuristicToolRoutingModel(), [new MathTool()]);
foreach ($agent->chatStream($argv[2] ?? 'calculate sqrt(81)+11') as $event) {
    echo '[' . $event->type . '] ';
    if ($event->type === 'token') {
        echo $event->data['token'] ?? '';
    } elseif ($event->type === 'final') {
        echo PHP_EOL . 'Final answer: ' . ($event->data['result']['answer'] ?? '') . PHP_EOL;
    } else {
        echo json_encode($event->data, JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
}
