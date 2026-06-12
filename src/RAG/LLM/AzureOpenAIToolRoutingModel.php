<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\LLM;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Exceptions\SerializationException;
use ML\IDEA\RAG\Agents\AgentUsage;
use ML\IDEA\RAG\Contracts\HttpTransportInterface;
use ML\IDEA\RAG\Contracts\ToolRoutingModelInterface;
use ML\IDEA\RAG\Contracts\UsageAwareToolRoutingModelInterface;
use ML\IDEA\RAG\Http\SimpleHttpTransport;

final class AzureOpenAIToolRoutingModel implements ToolRoutingModelInterface, UsageAwareToolRoutingModelInterface
{
    private AgentUsage $lastUsage;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $endpoint,
        private readonly string $deployment,
        private readonly string $apiVersion = '2024-02-15-preview',
        private readonly HttpTransportInterface $http = new SimpleHttpTransport(),
        private readonly float $costPer1kTokens = 0.0,
    ) {
        if ($this->apiKey === '' || $this->endpoint === '' || $this->deployment === '') {
            throw new InvalidArgumentException('Azure OpenAI apiKey, endpoint and deployment are required.');
        }
        $this->lastUsage = new AgentUsage();
    }

    public function respond(array $messages, array $tools): array
    {
        $url = sprintf(
            '%s/openai/deployments/%s/chat/completions?api-version=%s',
            rtrim($this->endpoint, '/'),
            rawurlencode($this->deployment),
            rawurlencode($this->apiVersion)
        );

        $providerTools = ProviderToolPayloadBuilder::openAiTools($tools);
        $body = [
            'messages' => $this->toProviderMessages($messages, $tools),
            // gpt-5 mini/nano Azure deployments currently only support the default temperature (1).
            'temperature' => $this->resolveTemperature(),
        ];
        if ($providerTools !== []) {
            $body['tools'] = $providerTools;
            $body['tool_choice'] = 'auto';
        }

        $response = $this->http->postJson($url, ['api-key' => $this->apiKey], $body);
        $this->lastUsage = isset($response['usage']) && is_array($response['usage'])
            ? AgentUsage::fromProviderUsage($response['usage'], $this->costPer1kTokens)
            : new AgentUsage();

        if (isset($response['error']) && is_array($response['error'])) {
            $message = isset($response['error']['message']) ? (string) $response['error']['message'] : 'Unknown Azure OpenAI error.';
            $code = isset($response['error']['code']) ? (string) $response['error']['code'] : 'unknown_error';
            throw new SerializationException(sprintf('Azure OpenAI request failed (%s): %s', $code, $message));
        }

        $message = isset($response['choices'][0]['message']) && is_array($response['choices'][0]['message'])
            ? $response['choices'][0]['message']
            : [];
        $toolDecision = ProviderToolCallParser::parseChatMessage($message);
        if ($toolDecision !== null) {
            return $toolDecision;
        }

        $content = (string) ($message['content'] ?? '');
        return ToolRoutingDecisionParser::parse($content);
    }

    public function lastUsage(): AgentUsage
    {
        return $this->lastUsage;
    }

    private function resolveTemperature(): float|int
    {
        $deployment = strtolower($this->deployment);
        if (str_contains($deployment, 'mini') || str_contains($deployment, 'nano')) {
            return 1;
        }

        return 0.1;
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<int, array<string, mixed>> $tools
     * @return array<int, array{role: string, content: string}>
     */
    private function toProviderMessages(array $messages, array $tools): array
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
            $role = $msg['role'];
            $content = $msg['content'];

            if ($role === 'tool') {
                if (isset($msg['tool_call_id'])) {
                    $out[] = ['role' => 'tool', 'tool_call_id' => (string) $msg['tool_call_id'], 'content' => $content];
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
}
