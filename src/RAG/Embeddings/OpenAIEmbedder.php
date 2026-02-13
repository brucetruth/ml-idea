<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Embeddings;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\RAG\Contracts\EmbedderInterface;
use ML\IDEA\RAG\Contracts\HttpTransportInterface;
use ML\IDEA\RAG\Http\SimpleHttpTransport;

final class OpenAIEmbedder implements EmbedderInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'text-embedding-3-small',
        private readonly string $baseUrl = 'https://api.openai.com/v1',
        private readonly HttpTransportInterface $http = new SimpleHttpTransport(),
    ) {
        if ($this->apiKey === '') {
            throw new InvalidArgumentException('OpenAI apiKey cannot be empty.');
        }
    }

    public function embed(string $text): array
    {
        $batch = $this->embedBatch([$text]);
        return $batch[0] ?? [];
    }

    public function embedBatch(array $texts): array
    {
        $response = $this->http->postJson(
            rtrim($this->baseUrl, '/') . '/embeddings',
            ['Authorization' => 'Bearer ' . $this->apiKey],
            ['model' => $this->model, 'input' => $texts],
        );

        if (!isset($response['data']) || !is_array($response['data'])) {
            throw new InvalidArgumentException('Invalid OpenAI embeddings response: missing data.');
        }

        $vectors = [];
        foreach ($response['data'] as $item) {
            if (!is_array($item) || !isset($item['embedding']) || !is_array($item['embedding'])) {
                throw new InvalidArgumentException('Invalid OpenAI embeddings response: malformed embedding item.');
            }
            $vectors[] = array_map(static fn ($v): float => (float) $v, $item['embedding']);
        }

        return $vectors;
    }
}
