<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\LLM;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\RAG\Agents\AgentUsage;
use ML\IDEA\RAG\Contracts\HttpTransportInterface;
use ML\IDEA\RAG\Contracts\ToolRoutingModelInterface;
use ML\IDEA\RAG\Contracts\UsageAwareToolRoutingModelInterface;
use ML\IDEA\RAG\Http\SimpleHttpTransport;

final class AnthropicToolRoutingModel implements ToolRoutingModelInterface, UsageAwareToolRoutingModelInterface
{
    private AgentUsage $lastUsage;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-3-5-sonnet-20240620',
        private readonly string $baseUrl = 'https://api.anthropic.com/v1',
        private readonly HttpTransportInterface $http = new SimpleHttpTransport(),
        private readonly float $costPer1kTokens = 0.0,
    ) {
        if ($this->apiKey === '') {
            throw new InvalidArgumentException('Anthropic apiKey cannot be empty.');
        }
        $this->lastUsage = new AgentUsage();
    }

    public function respond(array $messages, array $tools): array
    {
        [$system, $anthropicMessages] = $this->toAnthropicMessages($messages);
        $body = [
            'model' => $this->model,
            'max_tokens' => 1024,
            'system' => $system,
            'messages' => $anthropicMessages,
        ];

        $anthropicTools = ProviderToolPayloadBuilder::anthropicTools($tools);
        if ($anthropicTools !== []) {
            $body['tools'] = $anthropicTools;
        }

        $response = $this->http->postJson(
            rtrim($this->baseUrl, '/') . '/messages',
            [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
            ],
            $body,
        );

        $this->lastUsage = isset($response['usage']) && is_array($response['usage'])
            ? AgentUsage::fromAnthropicUsage($response['usage'], $this->costPer1kTokens)
            : new AgentUsage();

        /** @var array<int, array<string, mixed>> $content */
        $content = isset($response['content']) && is_array($response['content']) ? $response['content'] : [];
        $toolDecision = ProviderToolCallParser::parseAnthropicContent($content);
        if ($toolDecision !== null) {
            return $toolDecision;
        }

        return ToolRoutingDecisionParser::parse($this->concatTextBlocks($content));
    }

    public function lastUsage(): AgentUsage
    {
        return $this->lastUsage;
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array{0: string, 1: array<int, array<string, mixed>>}
     */
    private function toAnthropicMessages(array $messages): array
    {
        $systemParts = [];
        $out = [];

        foreach ($messages as $msg) {
            $role = isset($msg['role']) ? (string) $msg['role'] : 'user';
            $content = isset($msg['content']) ? (string) $msg['content'] : '';

            if ($role === 'system') {
                if ($content !== '') {
                    $systemParts[] = $content;
                }
                continue;
            }

            if ($role === 'assistant' && isset($msg['tool_calls']) && is_array($msg['tool_calls'])) {
                $blocks = [];
                if ($content !== '') {
                    $blocks[] = ['type' => 'text', 'text' => $content];
                }
                foreach ($msg['tool_calls'] as $call) {
                    if (!is_array($call)) {
                        continue;
                    }
                    $function = isset($call['function']) && is_array($call['function']) ? $call['function'] : [];
                    $name = isset($function['name']) ? (string) $function['name'] : '';
                    $arguments = isset($function['arguments']) ? (string) $function['arguments'] : '{}';
                    $input = json_decode($arguments, true);
                    $block = [
                        'type' => 'tool_use',
                        'id' => isset($call['id']) ? (string) $call['id'] : ('toolu_' . $name),
                        'name' => $name,
                        'input' => is_array($input) ? $input : [],
                    ];
                    $blocks[] = $block;
                }
                if ($blocks !== []) {
                    $out[] = ['role' => 'assistant', 'content' => $blocks];
                }
                continue;
            }

            if ($role === 'tool') {
                $out[] = [
                    'role' => 'user',
                    'content' => [[
                        'type' => 'tool_result',
                        'tool_use_id' => (string) ($msg['tool_call_id'] ?? ''),
                        'content' => $content,
                    ]],
                ];
                continue;
            }

            if ($role === 'assistant' || $role === 'user') {
                $out[] = ['role' => $role, 'content' => $content];
            }
        }

        return [implode("\n\n", $systemParts), $out];
    }

    /** @param array<int, array<string, mixed>> $content */
    private function concatTextBlocks(array $content): string
    {
        $parts = [];
        foreach ($content as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text') {
                $parts[] = (string) ($block['text'] ?? '');
            }
        }

        return trim(implode("\n", $parts));
    }
}
