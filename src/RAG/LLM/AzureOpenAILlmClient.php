<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\LLM;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\RAG\Contracts\HttpTransportInterface;
use ML\IDEA\RAG\Contracts\LlmClientInterface;
use ML\IDEA\RAG\Http\SimpleHttpTransport;

final class AzureOpenAILlmClient implements LlmClientInterface
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

    public function generate(string $prompt, array $options = []): string
    {
        $temperature = isset($options['temperature']) ? (float) $options['temperature'] : 0.2;
        $maxTokens = isset($options['max_tokens']) ? (int) $options['max_tokens'] : 700;

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
