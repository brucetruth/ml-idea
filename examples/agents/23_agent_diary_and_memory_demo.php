<?php

declare(strict_types=1);

/**
 * Diary + sticky notes demo.
 *
 * - AgentStateStore  = full session "diary" (messages, tool I/O) → JSON on disk
 * - AgentMemoryStore = compact tool episodes + summarizer → injected when routing
 *   context is windowed (diary too long for the model window)
 *
 * Run:
 *   php examples/agents/23_agent_diary_and_memory_demo.php
 *   php examples/agents/23_agent_diary_and_memory_demo.php my-session 8
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use ML\IDEA\RAG\Agents\AgentContextManager;
use ML\IDEA\RAG\Agents\AgentState;
use ML\IDEA\RAG\Agents\AgentStateStoreFactory;
use ML\IDEA\RAG\Agents\FileAgentMemoryStore;
use ML\IDEA\RAG\Agents\TruncatingEpisodicMemorySummarizer;
use ML\IDEA\RAG\Agents\ToolRoutingAgent;
use ML\IDEA\RAG\LLM\HeuristicToolRoutingModel;
use ML\IDEA\RAG\Tools\MathTool;

$sessionId = $argv[1] ?? 'diary-memory-demo';
$fillerTurns = isset($argv[2]) ? max(0, (int) $argv[2]) : 6;

$statePath = __DIR__ . '/../artifacts/agent_state';
$memoryPath = __DIR__ . '/../artifacts/agent_memory';

$stateStore = AgentStateStoreFactory::create([
    'driver' => 'file',
    'path' => $statePath,
]);
$memory = new FileAgentMemoryStore($memoryPath, new TruncatingEpisodicMemorySummarizer());

$agent = new ToolRoutingAgent(
    new HeuristicToolRoutingModel(),
    [new MathTool()],
    contextManager: new AgentContextManager(
        maxRoutingMessages: 8,
        maxToolMessageChars: 500,
        preserveInitialMessages: 1,
    ),
    stateStore: $stateStore,
    memoryStore: $memory,
);

echo 'Session: ' . $sessionId . PHP_EOL;
echo 'Diary path: ' . $statePath . PHP_EOL;
echo 'Memory path: ' . $memoryPath . PHP_EOL;
echo str_repeat('-', 60) . PHP_EOL;

echo PHP_EOL . 'Turn 1 — math tool (episode auto-appended to memory store):' . PHP_EOL;
$result = $agent->chatInSession($sessionId, 'sqrt(81)+11');
echo '  Answer: ' . $result['answer'] . PHP_EOL;
echo '  Episodes recorded: ' . count($memory->episodes($sessionId)) . PHP_EOL;

echo PHP_EOL . 'Filler turns — diary grows; routing window drops older messages:' . PHP_EOL;
for ($i = 0; $i < $fillerTurns; $i++) {
    $agent->chatInSession($sessionId, 'filler note ' . $i . ' ' . str_repeat('padding ', 24));
    $loaded = $stateStore->load($sessionId);
    $count = $loaded instanceof AgentState ? count($loaded->messages()) : 0;
    echo '  filler ' . ($i + 1) . '/' . $fillerTurns . ' — diary messages on disk: ' . $count . PHP_EOL;
}

$loaded = $stateStore->load($sessionId);
$diaryMessageCountBeforeFinal = $loaded instanceof AgentState ? count($loaded->messages()) : 0;

echo PHP_EOL . 'After windowing — sticky note the router would inject:' . PHP_EOL;
echo $memory->summarizeForContext($sessionId) . PHP_EOL;

echo PHP_EOL . 'Final turn (router sees trimmed messages + episodic summary above):' . PHP_EOL;
$final = $agent->chatInSession($sessionId, 'what tools ran earlier in this session?');
echo '  Answer: ' . $final['answer'] . PHP_EOL;

$loaded = $stateStore->load($sessionId);
$diaryMessageCount = $loaded instanceof AgentState ? count($loaded->messages()) : 0;
echo '  Diary messages (full state on disk): ' . $diaryMessageCount . PHP_EOL;
echo '  Episodes (compact memory on disk): ' . count($memory->episodes($sessionId)) . PHP_EOL;

$safeSession = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $sessionId) ?? 'session';
echo PHP_EOL . 'Inspect artifacts:' . PHP_EOL;
echo '  Diary:  ' . $statePath . '/' . $safeSession . '.json' . PHP_EOL;
echo '  Memory: ' . $memoryPath . '/' . $safeSession . '.json' . PHP_EOL;
