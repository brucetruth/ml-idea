<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ML\IDEA\RAG\Agents\JsonlAgentRunLogger;
use ML\IDEA\RAG\Agents\ToolRoutingAgent;
use ML\IDEA\RAG\Contracts\ToolRoutingModelInterface;
use ML\IDEA\RAG\Tools\MathTool;

$logPath = $argv[1] ?? sys_get_temp_dir() . '/mlidea-agent-runs-demo.jsonl';

$model = new class () implements ToolRoutingModelInterface {
    private int $turn = 0;

    public function respond(array $messages, array $tools): array
    {
        $this->turn++;

        return $this->turn === 1
            ? ['type' => 'tool_call', 'tool' => 'math', 'input' => ['expression' => '12*12']]
            : ['type' => 'final', 'content' => '144'];
    }
};

$logger = new JsonlAgentRunLogger($logPath);
$result = (new ToolRoutingAgent(
    $model,
    [new MathTool()],
    agentName: 'AuditDemo',
    agentRunLogger: $logger,
))->chat($argv[2] ?? 'What is 12 times 12?');

echo 'Answer: ' . $result['answer'] . PHP_EOL;
echo 'Stop reason: ' . $result['stop_reason'] . PHP_EOL;
echo 'Audit log: ' . $logPath . PHP_EOL;
echo PHP_EOL . 'Last line:' . PHP_EOL;

$lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$last = is_array($lines) && $lines !== [] ? end($lines) : '';
echo $last . PHP_EOL;
