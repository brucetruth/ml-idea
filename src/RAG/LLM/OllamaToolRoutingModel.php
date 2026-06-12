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
        private readonly bool $useNativeTools = true,
    ) {
    }

    public function respond(array $messages, array $tools): array
    {
        $body = [
            'model' => $this->model,
            'stream' => false,
            'messages' => $this->toProviderMessages($messages, $tools, nativeTools: $this->useNativeTools && $tools !== []),
        ];

        if ($this->useNativeTools && $tools !== []) {
            $body['tools'] = ProviderToolPayloadBuilder::ollamaTools($tools);
        }

        $response = $this->http->postJson(
            rtrim($this->baseUrl, '/') . '/api/chat',
            [],
            $body,
        );

        $message = isset($response['message']) && is_array($response['message']) ? $response['message'] : [];
        $toolDecision = ProviderToolCallParser::parseOllamaMessage($message);
        if ($toolDecision !== null) {
            return $toolDecision;
        }

        $content = (string) ($message['content'] ?? '');
        return ToolRoutingDecisionParser::parse($content);
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @param array<int, array<string, mixed>> $tools
     * @return array<int, array<string, mixed>>
     */
    private function toProviderMessages(array $messages, array $tools, bool $nativeTools): array
    {
        if ($nativeTools) {
            return $this->toNativeProviderMessages($messages);
        }

        return $this->toJsonPromptMessages($messages, $tools);
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, array<string, mixed>>
     */
    private function toNativeProviderMessages(array $messages): array
    {
        $out = [];
        foreach ($messages as $msg) {
            $role = isset($msg['role']) ? (string) $msg['role'] : 'user';
            $content = isset($msg['content']) ? (string) $msg['content'] : '';

            if ($role === 'tool') {
                if (isset($msg['tool_call_id'])) {
                    $out[] = ['role' => 'tool', 'content' => $content, 'tool_call_id' => (string) $msg['tool_call_id']];
                } else {
                    $out[] = ['role' => 'assistant', 'content' => 'TOOL_RESULT: ' . $content];
                }
                continue;
            }

            if ($role === 'assistant' && isset($msg['tool_calls']) && is_array($msg['tool_calls'])) {
                $out[] = ['role' => 'assistant', 'content' => $content, 'tool_calls' => $msg['tool_calls']];
                continue;
            }

            if (!in_array($role, ['system', 'user', 'assistant'], true)) {
                $role = 'user';
            }

            $out[] = ['role' => $role, 'content' => $content];
        }

        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @param array<int, array<string, mixed>> $tools
     * @return array<int, array<string, mixed>>
     */
    private function toJsonPromptMessages(array $messages, array $tools): array
    {
        $toolLines = [];
        foreach ($tools as $tool) {
            $line = sprintf('- %s: %s', (string) $tool['name'], (string) $tool['description']);
            if (isset($tool['input_schema'])) {
                $line .= ' Input schema: ' . json_encode($tool['input_schema'], JSON_THROW_ON_ERROR);
            }
            if (isset($tool['risk_level'])) {
                $line .= ' Risk: ' . (string) $tool['risk_level'];
            }
            $toolLines[] = $line;
        }

        $system = "You are a strict tool-routing controller.\n"
            . "Available tools:\n" . implode("\n", $toolLines) . "\n\n"
            . "Return JSON only:\n"
            . "{\"type\":\"tool_call\",\"tool\":\"name\",\"input\":{...}} OR "
            . "{\"type\":\"tool_calls\",\"tool_calls\":[{\"tool\":\"name\",\"input\":{...}}]} OR "
            . "{\"type\":\"clarify\",\"content\":\"question\"} OR {\"type\":\"refuse\",\"content\":\"reason\"} OR {\"type\":\"final\",\"content\":\"...\"}.";

        $out = [['role' => 'system', 'content' => $system]];
        foreach ($messages as $msg) {
            $role = isset($msg['role']) ? (string) $msg['role'] : 'user';
            $content = isset($msg['content']) ? (string) $msg['content'] : '';

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
