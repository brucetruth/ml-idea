<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\LLM;

final class ProviderToolCallParser
{
    /**
     * @param array<string, mixed> $message
     * @return array<string, mixed>|null
     */
    public static function parseChatMessage(array $message): ?array
    {
        if (isset($message['tool_calls']) && is_array($message['tool_calls']) && $message['tool_calls'] !== []) {
            $calls = [];
            foreach ($message['tool_calls'] as $call) {
                if (!is_array($call)) {
                    continue;
                }
                $function = isset($call['function']) && is_array($call['function']) ? $call['function'] : [];
                $id = isset($call['id']) ? (string) $call['id'] : '';
                $name = isset($function['name']) ? (string) $function['name'] : '';
                $arguments = isset($function['arguments']) ? (string) $function['arguments'] : '{}';
                $input = json_decode($arguments, true);
                if ($name !== '') {
                    $normalized = ['tool' => $name, 'input' => is_array($input) ? $input : []];
                    if ($id !== '') {
                        $normalized['provider_call_id'] = $id;
                    }
                    $calls[] = $normalized;
                }
            }

            if (count($calls) === 1) {
                $single = ['type' => 'tool_call', 'tool' => $calls[0]['tool'], 'input' => $calls[0]['input']];
                if (isset($calls[0]['provider_call_id'])) {
                    $single['provider_call_id'] = $calls[0]['provider_call_id'];
                }
                return $single;
            }

            if ($calls !== []) {
                return ['type' => 'tool_calls', 'tool_calls' => $calls];
            }
        }

        if (isset($message['function_call']) && is_array($message['function_call'])) {
            $function = $message['function_call'];
            $id = isset($function['id']) ? (string) $function['id'] : '';
            $name = isset($function['name']) ? (string) $function['name'] : '';
            $arguments = isset($function['arguments']) ? (string) $function['arguments'] : '{}';
            $input = json_decode($arguments, true);
            if ($name !== '') {
                $single = ['type' => 'tool_call', 'tool' => $name, 'input' => is_array($input) ? $input : []];
                if ($id !== '') {
                    $single['provider_call_id'] = $id;
                }
                return $single;
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $contentBlocks
     * @return array<string, mixed>|null
     */
    public static function parseAnthropicContent(array $contentBlocks): ?array
    {
        $calls = [];
        foreach ($contentBlocks as $block) {
            if (!is_array($block) || ($block['type'] ?? '') !== 'tool_use') {
                continue;
            }

            $name = isset($block['name']) ? (string) $block['name'] : '';
            /** @var array<string, mixed> $input */
            $input = isset($block['input']) && is_array($block['input']) ? $block['input'] : [];
            $id = isset($block['id']) ? (string) $block['id'] : '';
            if ($name === '') {
                continue;
            }

            $normalized = ['tool' => $name, 'input' => $input];
            if ($id !== '') {
                $normalized['provider_call_id'] = $id;
            }
            $calls[] = $normalized;
        }

        if (count($calls) === 1) {
            $single = ['type' => 'tool_call', 'tool' => $calls[0]['tool'], 'input' => $calls[0]['input']];
            if (isset($calls[0]['provider_call_id'])) {
                $single['provider_call_id'] = $calls[0]['provider_call_id'];
            }
            return $single;
        }

        if ($calls !== []) {
            return ['type' => 'tool_calls', 'tool_calls' => $calls];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $message
     * @return array<string, mixed>|null
     */
    public static function parseOllamaMessage(array $message): ?array
    {
        return self::parseChatMessage($message);
    }
}
