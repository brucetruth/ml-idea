<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\LLM;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\RAG\Contracts\HttpTransportInterface;
use ML\IDEA\RAG\Contracts\ToolRoutingModelInterface;
use ML\IDEA\RAG\Http\SimpleHttpTransport;

final class OpenAIToolRoutingModel implements ToolRoutingModelInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gpt-4o-mini',
        private readonly string $baseUrl = 'https://api.openai.com/v1',
        private readonly HttpTransportInterface $http = new SimpleHttpTransport(),
    ) {
        if ($this->apiKey === '') {
            throw new InvalidArgumentException('OpenAI apiKey cannot be empty.');
        }
    }

    public function respond(array $messages, array $tools): array
    {
        $response = $this->http->postJson(
            rtrim($this->baseUrl, '/') . '/chat/completions',
            ['Authorization' => 'Bearer ' . $this->apiKey],
            [
                'model' => $this->model,
                'messages' => $this->toProviderMessages($messages, $tools),
                'temperature' => 0.1,
            ]
        );

        $content = (string) ($response['choices'][0]['message']['content'] ?? '');
        return ToolRoutingDecisionParser::parse($content);
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<int, array{name: string, description: string}> $tools
     * @return array<int, array{role: string, content: string}>
     */
    private function toProviderMessages(array $messages, array $tools): array
    {
        $toolLines = [];
        foreach ($tools as $tool) {
            $toolLines[] = sprintf('- %s: %s', $tool['name'], $tool['description']);
        }

        $system = "You are a strict tool-routing controller.\n"
            . "Available tools:\n" . implode("\n", $toolLines) . "\n\n"
            . "Return JSON only:\n"
            . "{\"type\":\"tool_call\",\"tool\":\"name\",\"input\":{...}} OR {\"type\":\"final\",\"content\":\"...\"}.";

        $out = [['role' => 'system', 'content' => $system]];
        foreach ($messages as $msg) {
            $role = $msg['role'];
            $content = $msg['content'];

            if ($role === 'tool') {
                $out[] = ['role' => 'assistant', 'content' => 'TOOL_RESULT: ' . $content];
                continue;
            }

            if (!in_array($role, ['system', 'user', 'assistant'], true)) {
                $role = 'user';
            }

            $out[] = ['role' => $role, 'content' => $content];
        }

        return $out;
    }
}
