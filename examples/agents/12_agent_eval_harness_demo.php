<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ML\IDEA\RAG\Agents\AgentEvalHarness;
use ML\IDEA\RAG\Agents\ToolRoutingAgent;
use ML\IDEA\RAG\LLM\HeuristicToolRoutingModel;
use ML\IDEA\RAG\Tools\MathTool;
use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Contracts\ToolSchemaInterface;

$weather = new class () implements ToolInterface, ToolSchemaInterface {
    public function name(): string { return 'weather'; }
    public function description(): string { return 'Weather stub for eval demo.'; }
    public function invoke(array $input): string { return json_encode(['weather' => $input], JSON_THROW_ON_ERROR); }
    public function inputSchema(): array { return ['type' => 'object', 'required' => ['lat', 'lon'], 'properties' => ['lat' => ['type' => 'number'], 'lon' => ['type' => 'number']]]; }
    public function examples(): array { return [['lat' => -15.3, 'lon' => 28.3]]; }
    public function riskLevel(): string { return 'low'; }
};

$agent = new ToolRoutingAgent(new HeuristicToolRoutingModel(), [new MathTool(), $weather]);
$harness = new AgentEvalHarness();
$cases = $harness->loadCasesFromJson(__DIR__ . '/../../tests/fixtures/agent_eval_heuristic.json');
$results = $harness->run($agent, $cases);
$summary = $harness->summarize($results);

echo 'Agent eval summary' . PHP_EOL;
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL . PHP_EOL;

foreach ($results as $result) {
    echo ($result->passed ? '[PASS] ' : '[FAIL] ') . $result->name . PHP_EOL;
    if (!$result->passed) {
        echo '  ' . $result->message . PHP_EOL;
    }
}
