<?php

declare(strict_types=1);

namespace ML\IDEA\Vision\Embeddings;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\RAG\Contracts\HttpTransportInterface;
use ML\IDEA\RAG\Http\SimpleHttpTransport;
use ML\IDEA\Vision\Contracts\VisionEmbedderInterface;

/** Ollama multimodal /api/embed client for image vectors (llava, mllama, etc.). */
final class OllamaVisionEmbedder implements VisionEmbedderInterface
{
    public function __construct(
        private readonly string $model = 'llava',
        private readonly string $baseUrl = 'http://localhost:11434',
        private readonly HttpTransportInterface $http = new SimpleHttpTransport(),
    ) {
    }

    public function embedImage(string $path): array
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException(sprintf('Image file not found: %s', $path));
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new InvalidArgumentException(sprintf('Unable to read image: %s', $path));
        }

        $base64 = base64_encode($raw);
        $response = $this->http->postJson(
            rtrim($this->baseUrl, '/') . '/api/embed',
            [],
            [
                'model' => $this->model,
                'input' => basename($path),
                'images' => [$base64],
            ],
        );

        return self::parseEmbedding($response);
    }

    public function embedBatch(array $paths): array
    {
        $vectors = [];
        foreach ($paths as $path) {
            $vectors[] = $this->embedImage($path);
        }

        return $vectors;
    }

    /** @return array<int, float> */
    private static function parseEmbedding(mixed $response): array
    {
        if (!is_array($response)) {
            throw new InvalidArgumentException('Invalid Ollama vision embed response.');
        }

        if (isset($response['embeddings'][0]) && is_array($response['embeddings'][0])) {
            return array_map(static fn ($v): float => (float) $v, $response['embeddings'][0]);
        }

        if (isset($response['embedding']) && is_array($response['embedding'])) {
            return array_map(static fn ($v): float => (float) $v, $response['embedding']);
        }

        throw new InvalidArgumentException('Invalid Ollama vision embed response: missing embedding.');
    }
}
