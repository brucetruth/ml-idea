<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Embeddings;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\RAG\Contracts\EmbedderInterface;
use ML\IDEA\RAG\Contracts\HttpTransportInterface;
use ML\IDEA\RAG\Http\SimpleHttpTransport;

final class AzureOpenAIEmbedder implements EmbedderInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $endpoint,
        private readonly string $deployment,
        private readonly string $apiVersion = '2024-02-15-preview',
        private readonly HttpTransportInterface $http = new SimpleHttpTransport(),
    ) {
        if ($this->apiKey === '' || $this->endpoint === '' || $this->deployment === '') {
            throw new InvalidArgumentException('Azure OpenAI apiKey, endpoint, and deployment are required.');
        }
    }

    public function embed(string $text): array
    {
        $batch = $this->embedBatch([$text]);
        return $batch[0] ?? [];
    }

    public function embedBatch(array $texts): array
    {
        $url = sprintf(
            '%s/openai/deployments/%s/embeddings?api-version=%s',
            rtrim($this->endpoint, '/'),
            rawurlencode($this->deployment),
            rawurlencode($this->apiVersion)
        );

        $response = $this->http->postJson(
            $url,
            ['api-key' => $this->apiKey],
            ['input' => $texts],
        );

        if (!isset($response['data']) || !is_array($response['data'])) {
            throw new InvalidArgumentException('Invalid Azure OpenAI embeddings response: missing data.');
        }

        $vectors = [];
        foreach ($response['data'] as $item) {
            if (!is_array($item) || !isset($item['embedding']) || !is_array($item['embedding'])) {
                throw new InvalidArgumentException('Invalid Azure OpenAI embeddings response: malformed embedding item.');
            }
            $vectors[] = array_map(static fn ($v): float => (float) $v, $item['embedding']);
        }

        return $vectors;
    }
}
