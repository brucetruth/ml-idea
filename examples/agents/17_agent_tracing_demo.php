<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ML\IDEA\RAG\Agents\RecordingAgentTracer;
use ML\IDEA\RAG\Agents\ToolRoutingAgent;
use ML\IDEA\RAG\Contracts\ToolRoutingModelInterface;
use ML\IDEA\RAG\Tools\MathTool;

$model = new class () implements ToolRoutingModelInterface {
    private int $turn = 0;

    public function respond(array $messages, array $tools): array
    {
        $this->turn++;
        return $this->turn === 1
            ? ['type' => 'tool_call', 'tool' => 'math', 'input' => ['expression' => '9*9']]
            : ['type' => 'final', 'content' => '81'];
    }
};

$tracer = new RecordingAgentTracer();
$result = (new ToolRoutingAgent($model, [new MathTool()], agentTracer: $tracer))->chat($argv[1] ?? 'What is 9*9?');

echo 'Answer: ' . $result['answer'] . PHP_EOL;
echo 'Telemetry: ' . json_encode($result['telemetry'] ?? [], JSON_THROW_ON_ERROR) . PHP_EOL;
echo 'Recorded spans:' . PHP_EOL;
foreach ($tracer->spans() as $span) {
    echo sprintf(
        '  - %s (%dms, status=%s)' . PHP_EOL,
        $span['name'],
        $span['duration_ms'],
        $span['status']
    );
}

echo PHP_EOL . 'For production export, pass OpenTelemetryAgentTracer with a configured SDK tracer.' . PHP_EOL;
