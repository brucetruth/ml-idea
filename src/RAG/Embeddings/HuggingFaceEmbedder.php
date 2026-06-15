<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Embeddings;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\RAG\Contracts\EmbedderInterface;
use ML\IDEA\RAG\Contracts\HttpTransportInterface;
use ML\IDEA\RAG\Http\SimpleHttpTransport;

/** Hugging Face feature-extraction / TEI embeddings for RAG (remote API or local server). */
final class HuggingFaceEmbedder implements EmbedderInterface
{
    public function __construct(
        private readonly string $model,
        private readonly string $apiToken = '',
        private readonly string $apiUrl = 'https://api-inference.huggingface.co/models',
        private readonly HttpTransportInterface $http = new SimpleHttpTransport(),
    ) {
        if ($this->model === '') {
            throw new InvalidArgumentException('HuggingFace model id cannot be empty.');
        }
    }

    public function embed(string $text): array
    {
        $batch = $this->embedBatch([$text]);

        return $batch[0] ?? [];
    }

    public function embedBatch(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $headers = $this->apiToken !== '' ? ['Authorization' => 'Bearer ' . $this->apiToken] : [];
        $url = rtrim($this->apiUrl, '/') . '/' . ltrim($this->model, '/');

        $response = $this->http->postJson(
            $url,
            $headers,
            ['inputs' => count($texts) === 1 ? $texts[0] : $texts],
        );

        return array_map(
            static fn (mixed $item): array => self::parseVector($item),
            self::normalizeResponseItems($response),
        );
    }

    /** @return array<int, mixed> */
    private static function normalizeResponseItems(mixed $response): array
    {
        if (!is_array($response)) {
            throw new InvalidArgumentException('Invalid HuggingFace embeddings response.');
        }

        if ($response === []) {
            return [];
        }

        if (isset($response[0]) && is_numeric($response[0])) {
            return [$response];
        }

        if (isset($response[0][0]) && is_array($response[0][0])) {
            if (isset($response[0][0][0]) && is_numeric($response[0][0][0])) {
                return array_map(static fn (array $tokens): array => self::meanPoolTokens($tokens), $response);
            }

            return [self::meanPoolTokens($response[0])];
        }

        if (isset($response[0]) && is_array($response[0])) {
            return $response;
        }

        throw new InvalidArgumentException('Unsupported HuggingFace embeddings response shape.');
    }

    /** @param array<int, array<int, float|int>> $tokens */
    private static function meanPoolTokens(array $tokens): array
    {
        if ($tokens === []) {
            return [];
        }

        if (is_numeric($tokens[0])) {
            return array_map(static fn ($v): float => (float) $v, $tokens);
        }

        $dims = count($tokens[0]);
        $sum = array_fill(0, $dims, 0.0);
        foreach ($tokens as $token) {
            foreach ($token as $j => $value) {
                $sum[$j] += (float) $value;
            }
        }

        $n = count($tokens);

        return array_map(static fn (float $v): float => $v / $n, $sum);
    }

    private static function parseVector(mixed $item): array
    {
        if (!is_array($item)) {
            throw new InvalidArgumentException('Malformed HuggingFace embedding item.');
        }

        if (isset($item['embedding']) && is_array($item['embedding'])) {
            return array_map(static fn ($v): float => (float) $v, $item['embedding']);
        }

        if ($item !== [] && is_numeric($item[0])) {
            return array_map(static fn ($v): float => (float) $v, $item);
        }

        throw new InvalidArgumentException('Malformed HuggingFace embedding item.');
    }
}
