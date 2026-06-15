<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Embeddings;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\RAG\Contracts\EmbedderInterface;
use ML\IDEA\RAG\Contracts\HttpTransportInterface;
use ML\IDEA\RAG\Http\SimpleHttpTransport;

/** OpenAI-compatible /v1/embeddings client (Text Embeddings Inference, vLLM, etc.). */
final class TeiEmbedder implements EmbedderInterface
{
    public function __construct(
        private readonly string $model,
        private readonly string $baseUrl = 'http://localhost:8080/v1',
        private readonly ?string $apiKey = null,
        private readonly HttpTransportInterface $http = new SimpleHttpTransport(),
    ) {
        if ($this->model === '') {
            throw new InvalidArgumentException('TEI model name cannot be empty.');
        }
    }

    public function embed(string $text): array
    {
        $batch = $this->embedBatch([$text]);

        return $batch[0] ?? [];
    }

    public function embedBatch(array $texts): array
    {
        $headers = $this->apiKey !== null && $this->apiKey !== ''
            ? ['Authorization' => 'Bearer ' . $this->apiKey]
            : [];

        $response = $this->http->postJson(
            rtrim($this->baseUrl, '/') . '/embeddings',
            $headers,
            ['model' => $this->model, 'input' => count($texts) === 1 ? $texts[0] : $texts],
        );

        if (!isset($response['data']) || !is_array($response['data'])) {
            throw new InvalidArgumentException('Invalid TEI embeddings response: missing data.');
        }

        $vectors = [];
        foreach ($response['data'] as $item) {
            if (!is_array($item) || !isset($item['embedding']) || !is_array($item['embedding'])) {
                throw new InvalidArgumentException('Invalid TEI embeddings response: malformed embedding item.');
            }
            $vectors[] = array_map(static fn ($v): float => (float) $v, $item['embedding']);
        }

        return $vectors;
    }
}
