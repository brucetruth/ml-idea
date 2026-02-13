<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\LLM;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\RAG\Contracts\HttpTransportInterface;
use ML\IDEA\RAG\Contracts\ToolRoutingModelInterface;
use ML\IDEA\RAG\Http\SimpleHttpTransport;

final class AzureOpenAIToolRoutingModel implements ToolRoutingModelInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $endpoint,
        private readonly string $deployment,
        private readonly string $apiVersion = '2024-02-15-preview',
        private readonly HttpTransportInterface $http = new SimpleHttpTransport(),
    ) {
        if ($this->apiKey === '' || $this->endpoint === '' || $this->deployment === '') {
            throw new InvalidArgumentException('Azure OpenAI apiKey, endpoint and deployment are required.');
        }
    }

    public function respond(array $messages, array $tools): array
    {
        $url = sprintf(
            '%s/openai/deployments/%s/chat/completions?api-version=%s',
            rtrim($this->endpoint, '/'),
            rawurlencode($this->deployment),
            rawurlencode($this->apiVersion)
        );

        $response = $this->http->postJson(
            $url,
            ['api-key' => $this->apiKey],
            [
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
