<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ML\IDEA\RAG\Agents\ToolRoutingAgent;
use ML\IDEA\RAG\Contracts\HttpTransportInterface;
use ML\IDEA\RAG\Contracts\ToolRoutingModelInterface;
use ML\IDEA\RAG\Http\SimpleHttpTransport;
use ML\IDEA\RAG\LLM\AnthropicToolRoutingModel;
use ML\IDEA\RAG\LLM\HeuristicToolRoutingModel;
use ML\IDEA\RAG\Tools\MathTool;
use ML\IDEA\RAG\Tools\WeatherTool;

/**
 * Example 08:
 * Provider-backed ToolRoutingModel (Anthropic) with heuristic fallback.
 *
 * Env:
 * - ANTHROPIC_API_KEY=... (or CLAUDE_API_KEY for backward compatibility)
 * - ANTHROPIC_MODEL=claude-3-5-sonnet-20240620
 */
$anthropicApiKey = (string) (getenv('ANTHROPIC_API_KEY') ?: getenv('CLAUDE_API_KEY') ?: '');
$anthropicModel = (string) (getenv('ANTHROPIC_MODEL') ?: getenv('CLAUDE_MODEL') ?: 'claude-3-5-sonnet-20240620');

$router = $anthropicApiKey !== ''
    ? new AnthropicToolRoutingModel($anthropicApiKey, $anthropicModel)
    : new HeuristicToolRoutingModel();

$agent = new ToolRoutingAgent(
    $router,
    [new WeatherTool(), new MathTool()]
);

$query = $argv[1] ?? 'What is the weather in Lusaka right now?';
$result = $agent->chat($query);

echo "Example 08 - Anthropic ToolRoutingModel\n";
echo 'Router: ' . ($anthropicApiKey !== '' ? 'Anthropic API' : 'Heuristic fallback (set ANTHROPIC_API_KEY to use Anthropic)') . PHP_EOL;
echo 'Q: ' . $query . PHP_EOL;
echo 'Answer: ' . $result['answer'] . PHP_EOL;
echo 'Tool calls: ' . json_encode($result['tool_calls'], JSON_THROW_ON_ERROR) . PHP_EOL;
