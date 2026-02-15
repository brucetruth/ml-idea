<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\LLM;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\RAG\Contracts\HttpTransportInterface;
use ML\IDEA\RAG\Contracts\LlmClientInterface;
use ML\IDEA\RAG\Http\SimpleHttpTransport;

final class OpenAILlmClient implements LlmClientInterface
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

    public function generate(string $prompt, array $options = []): string
    {
        $temperature = isset($options['temperature']) ? (float) $options['temperature'] : 0.2;
        $maxTokens = isset($options['max_tokens']) ? (int) $options['max_tokens'] : 700;

        $response = $this->http->postJson(
            rtrim($this->baseUrl, '/') . '/chat/completions',
            ['Authorization' => 'Bearer ' . $this->apiKey],
            [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ]
        );

        return (string) ($response['choices'][0]['message']['content'] ?? '');
    }
}
