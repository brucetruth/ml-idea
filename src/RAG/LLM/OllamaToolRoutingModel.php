<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\LLM;

use ML\IDEA\RAG\Contracts\HttpTransportInterface;
use ML\IDEA\RAG\Contracts\ToolRoutingModelInterface;
use ML\IDEA\RAG\Http\SimpleHttpTransport;

final class OllamaToolRoutingModel implements ToolRoutingModelInterface
{
    public function __construct(
        private readonly string $model = 'llama3.1',
        private readonly string $baseUrl = 'http://localhost:11434',
        private readonly HttpTransportInterface $http = new SimpleHttpTransport(),
    ) {
    }

    public function respond(array $messages, array $tools): array
    {
        $response = $this->http->postJson(
            rtrim($this->baseUrl, '/') . '/api/chat',
            [],
            [
                'model' => $this->model,
                'stream' => false,
                'messages' => $this->toProviderMessages($messages, $tools),
            ]
        );

        $content = (string) ($response['message']['content'] ?? '');
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
