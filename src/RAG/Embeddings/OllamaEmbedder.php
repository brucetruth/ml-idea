<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Embeddings;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\RAG\Contracts\EmbedderInterface;
use ML\IDEA\RAG\Contracts\HttpTransportInterface;
use ML\IDEA\RAG\Http\SimpleHttpTransport;

final class OllamaEmbedder implements EmbedderInterface
{
    public function __construct(
        private readonly string $model = 'nomic-embed-text',
        private readonly string $baseUrl = 'http://localhost:11434',
        private readonly HttpTransportInterface $http = new SimpleHttpTransport(),
    ) {
    }

    public function embed(string $text): array
    {
        $response = $this->http->postJson(
            rtrim($this->baseUrl, '/') . '/api/embeddings',
            [],
            ['model' => $this->model, 'prompt' => $text],
        );

        if (!isset($response['embedding']) || !is_array($response['embedding'])) {
            throw new InvalidArgumentException('Invalid Ollama embeddings response: missing embedding.');
        }

        return array_map(static fn ($v): float => (float) $v, $response['embedding']);
    }

    public function embedBatch(array $texts): array
    {
        $vectors = [];
        foreach ($texts as $text) {
            $vectors[] = $this->embed($text);
        }

        return $vectors;
    }
}
