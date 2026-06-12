<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ML\IDEA\RAG\Agents\AgentHandoffRegistry;
use ML\IDEA\RAG\Agents\ToolRoutingAgent;
use ML\IDEA\RAG\Contracts\ToolRoutingModelInterface;
use ML\IDEA\RAG\Tools\MathTool;

$mathModel = new class () implements ToolRoutingModelInterface {
    private int $turn = 0;

    public function respond(array $messages, array $tools): array
    {
        $this->turn++;
        return $this->turn === 1
            ? ['type' => 'tool_call', 'tool' => 'math', 'input' => ['expression' => '15*3']]
            : ['type' => 'final', 'content' => '15*3 equals 45'];
    }
};

$supervisorModel = new class () implements ToolRoutingModelInterface {
    private int $turn = 0;

    public function respond(array $messages, array $tools): array
    {
        $this->turn++;
        if ($this->turn === 1) {
            return ['type' => 'handoff', 'agent' => 'math_expert', 'content' => 'calculate 15*3'];
        }

        return ['type' => 'final', 'content' => 'The math expert confirmed: 45'];
    }
};

$registry = new AgentHandoffRegistry();
$registry->register(
    'math_expert',
    new ToolRoutingAgent($mathModel, [new MathTool()], agentName: 'MathExpert'),
    'Performs precise arithmetic with the math tool'
);

$supervisor = new ToolRoutingAgent(
    $supervisorModel,
    [],
    agentName: 'Supervisor',
    agentFeatures: ['delegates arithmetic to specialists'],
    handoffRegistry: $registry,
);

$result = $supervisor->chat($argv[1] ?? 'What is 15 times 3?');

echo 'Answer: ' . $result['answer'] . PHP_EOL;
echo 'Handoffs: ' . count($result['handoffs']) . PHP_EOL;
foreach ($result['handoffs'] as $handoff) {
    echo sprintf(
        '  - %s: %s -> %s' . PHP_EOL,
        $handoff['agent'],
        $handoff['task'],
        $handoff['answer']
    );
}
