<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\LLM;

final class ProviderToolPayloadBuilder
{
    /**
     * @param array<int, array<string, mixed>> $tools
     * @return array<int, array<string, mixed>>
     */
    public static function openAiTools(array $tools): array
    {
        $out = [];
        foreach ($tools as $tool) {
            $name = isset($tool['name']) ? (string) $tool['name'] : '';
            if ($name === '') {
                continue;
            }

            $schema = isset($tool['input_schema']) && is_array($tool['input_schema'])
                ? $tool['input_schema']
                : ['type' => 'object', 'properties' => new \stdClass()];

            $out[] = [
                'type' => 'function',
                'function' => [
                    'name' => $name,
                    'description' => isset($tool['description']) ? (string) $tool['description'] : '',
                    'parameters' => $schema,
                ],
            ];
        }

        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $tools
     * @return array<int, array<string, mixed>>
     */
    public static function anthropicTools(array $tools): array
    {
        $out = [];
        foreach ($tools as $tool) {
            $name = isset($tool['name']) ? (string) $tool['name'] : '';
            if ($name === '') {
                continue;
            }

            $schema = isset($tool['input_schema']) && is_array($tool['input_schema'])
                ? $tool['input_schema']
                : ['type' => 'object', 'properties' => new \stdClass()];

            $out[] = [
                'name' => $name,
                'description' => isset($tool['description']) ? (string) $tool['description'] : '',
                'input_schema' => $schema,
            ];
        }

        return $out;
    }

    /**
     * Ollama uses the same function-tool envelope as OpenAI-compatible APIs.
     *
     * @param array<int, array<string, mixed>> $tools
     * @return array<int, array<string, mixed>>
     */
    public static function ollamaTools(array $tools): array
    {
        return self::openAiTools($tools);
    }
}

