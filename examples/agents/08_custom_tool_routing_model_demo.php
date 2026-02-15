<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ML\IDEA\RAG\Agents\ToolRoutingAgent;
use ML\IDEA\RAG\Contracts\HttpTransportInterface;
use ML\IDEA\RAG\Contracts\ToolRoutingModelInterface;
use ML\IDEA\RAG\Http\SimpleHttpTransport;
use ML\IDEA\RAG\LLM\HeuristicToolRoutingModel;
use ML\IDEA\RAG\LLM\ToolRoutingDecisionParser;
use ML\IDEA\RAG\Tools\MathTool;
use ML\IDEA\RAG\Tools\WeatherTool;

/**
 * Example 08:
 * Build a custom provider-backed ToolRoutingModel (Claude-style).
 *
 * Env:
 * - CLAUDE_API_KEY=...
 * - CLAUDE_MODEL=claude-3-5-sonnet-20240620 (or newer)
 *
 * Fallback:
 * - If CLAUDE_API_KEY is missing, this demo falls back to HeuristicToolRoutingModel.
 */
final class ClaudeAIToolRoutingModel implements ToolRoutingModelInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-3-5-sonnet-20240620',
        private readonly string $baseUrl = 'https://api.anthropic.com/v1',
        private readonly HttpTransportInterface $http = new SimpleHttpTransport(),
    ) {
    }

    public function respond(array $messages, array $tools): array
    {
        $response = $this->http->postJson(
            rtrim($this->baseUrl, '/') . '/messages',
            [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
            ],
            [
                'model' => $this->model,
                'max_tokens' => 300,
                'system' => $this->buildSystemPrompt($tools),
                'messages' => [
                    ['role' => 'user', 'content' => $this->flattenMessages($messages)],
                ],
            ]
        );

        $raw = (string) ($response['content'][0]['text'] ?? '');
        return ToolRoutingDecisionParser::parse($raw);
    }

    /** @param array<int, array{name: string, description: string}> $tools */
    private function buildSystemPrompt(array $tools): string
    {
        $toolLines = [];
        foreach ($tools as $tool) {
            $toolLines[] = sprintf('- %s: %s', (string) ($tool['name'] ?? ''), (string) ($tool['description'] ?? ''));
        }

        return "You are a strict tool-routing controller.\n"
            . "Available tools:\n" . implode("\n", $toolLines) . "\n\n"
            . "Return JSON only:\n"
            . "{\"type\":\"tool_call\",\"tool\":\"name\",\"input\":{...}} OR {\"type\":\"final\",\"content\":\"...\"}.";
    }

    /** @param array<int, array{role: string, content: string}> $messages */
    private function flattenMessages(array $messages): string
    {
        $lines = [];
        foreach ($messages as $m) {
            $role = (string) ($m['role'] ?? 'user');
            $content = (string) ($m['content'] ?? '');
            $lines[] = strtoupper($role) . ': ' . $content;
        }

        return implode("\n", $lines);
    }
}

$claudeApiKey = (string) (getenv('CLAUDE_API_KEY') ?: '');
$claudeModel = (string) (getenv('CLAUDE_MODEL') ?: 'claude-3-5-sonnet-20240620');

$router = $claudeApiKey !== ''
    ? new ClaudeAIToolRoutingModel($claudeApiKey, $claudeModel)
    : new HeuristicToolRoutingModel();

$agent = new ToolRoutingAgent(
    $router,
    [new WeatherTool(), new MathTool()]
);

$query = $argv[1] ?? 'What is the weather in Lusaka right now?';
$result = $agent->chat($query);

echo "Example 08 - Custom Claude-style ToolRoutingModel\n";
echo 'Router: ' . ($claudeApiKey !== '' ? 'Claude API' : 'Heuristic fallback (set CLAUDE_API_KEY to use Claude)') . PHP_EOL;
echo 'Q: ' . $query . PHP_EOL;
echo 'Answer: ' . $result['answer'] . PHP_EOL;
echo 'Tool calls: ' . json_encode($result['tool_calls'], JSON_THROW_ON_ERROR) . PHP_EOL;
