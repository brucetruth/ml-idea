<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ML\IDEA\Examples\AiAdmin\Support\AdminHeuristicRouter;
use ML\IDEA\Examples\AiAdmin\Support\AdminToolset;
use ML\IDEA\RAG\Agents\AgentEvalHarness;
use ML\IDEA\RAG\Agents\ToolRoutingAgent;

$fixture = $argv[1] ?? __DIR__ . '/../../tests/fixtures/agent_eval_ai_admin.json';
$minPassRate = isset($argv[2]) ? (float) $argv[2] : 1.0;

$agent = new ToolRoutingAgent(
    new AdminHeuristicRouter(),
    AdminToolset::makeAutonomous(),
    agentName: 'AiAdminEval',
);

$harness = new AgentEvalHarness();
$cases = $harness->loadCasesFromJson($fixture);
$results = $harness->run($agent, $cases);
$summary = $harness->summarize($results);

echo 'Fixture: ' . $fixture . PHP_EOL;
echo sprintf('Pass rate: %.2f%% (%d/%d)' . PHP_EOL, $summary['pass_rate'] * 100, $summary['passed'], $summary['total']);

foreach ($results as $result) {
    $status = $result->passed ? 'PASS' : 'FAIL';
    echo sprintf('  [%s] %s%s', $status, $result->name, $result->passed ? '' : ' — ' . $result->message) . PHP_EOL;
}

if ($summary['pass_rate'] < $minPassRate) {
    exit(1);
}
