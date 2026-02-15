<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\LLM;

use ML\IDEA\RAG\Contracts\HttpTransportInterface;
use ML\IDEA\RAG\Contracts\LlmClientInterface;
use ML\IDEA\RAG\Http\SimpleHttpTransport;

final class OllamaLlmClient implements LlmClientInterface
{
    public function __construct(
        private readonly string $model = 'llama3.1',
        private readonly string $baseUrl = 'http://localhost:11434',
        private readonly HttpTransportInterface $http = new SimpleHttpTransport(),
    ) {
    }

    public function generate(string $prompt, array $options = []): string
    {
        $response = $this->http->postJson(
            rtrim($this->baseUrl, '/') . '/api/chat',
            [],
            [
                'model' => $this->model,
                'stream' => false,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]
        );

        return (string) ($response['message']['content'] ?? '');
    }
}
