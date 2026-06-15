<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ML\IDEA\RAG\Agents\ParallelToolCallRunner;
use ML\IDEA\RAG\Tools\MathTool;

$math = new MathTool();
$calls = [
    ['tool' => 'math', 'input' => ['expression' => '2+2']],
    ['tool' => 'math', 'input' => ['expression' => 'sqrt(81)+11']],
    ['tool' => 'math', 'input' => ['expression' => 'sin(pi/2)']],
];

$autoload = __DIR__ . '/../../vendor/autoload.php';
$runner = new ParallelToolCallRunner(true, is_file($autoload) ? $autoload : null);

echo 'ext-parallel available: ' . (ParallelToolCallRunner::isAvailable() ? 'yes' : 'no') . PHP_EOL;
echo 'Batch parallelizable: ' . ($runner->canParallelizeBatch($calls, ['math' => $math]) ? 'yes' : 'no') . PHP_EOL;

$batch = $runner->run(
    $calls,
    ['math' => $math],
    fn (array $call): string => $math->invoke($call['input']),
);

echo 'Execution mode: ' . $batch['mode'] . PHP_EOL;
foreach ($batch['outputs'] as $i => $output) {
    echo sprintf("  [%d] %s\n", $i, $output);
}
