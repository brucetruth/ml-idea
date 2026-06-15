<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\RAG\Contracts\HttpTransportInterface;
use ML\IDEA\RAG\Embeddings\AzureOpenAIEmbedder;
use ML\IDEA\RAG\Embeddings\HuggingFaceEmbedder;
use ML\IDEA\RAG\Embeddings\OllamaEmbedder;
use ML\IDEA\RAG\Embeddings\TeiEmbedder;
use ML\IDEA\RAG\Embeddings\OpenAIEmbedder;
use PHPUnit\Framework\TestCase;

final class RagEmbeddersTest extends TestCase
{
    public function testOpenAiEmbedderParsesBatchResponse(): void
    {
        $http = new class () implements HttpTransportInterface {
            public function postJson(string $url, array $headers, array $jsonBody): array
            {
                return ['data' => [['embedding' => [0.1, 0.2]], ['embedding' => [0.3, 0.4]]]];
            }
        };

        $embedder = new OpenAIEmbedder('key', 'text-embedding-3-small', 'https://api.openai.com/v1', $http);
        $vectors = $embedder->embedBatch(['a', 'b']);
        self::assertCount(2, $vectors);
        self::assertSame([0.1, 0.2], $vectors[0]);
    }

    public function testAzureEmbedderParsesResponse(): void
    {
        $http = new class () implements HttpTransportInterface {
            public function postJson(string $url, array $headers, array $jsonBody): array
            {
                return ['data' => [['embedding' => [0.9, 0.8]]]];
            }
        };

        $embedder = new AzureOpenAIEmbedder('key', 'https://example.openai.azure.com', 'embed-deploy', '2024-02-15-preview', $http);
        $vector = $embedder->embed('hello');
        self::assertSame([0.9, 0.8], $vector);
    }

    public function testOllamaEmbedderParsesResponse(): void
    {
        $http = new class () implements HttpTransportInterface {
            public function postJson(string $url, array $headers, array $jsonBody): array
            {
                return ['embedding' => [0.7, 0.6, 0.5]];
            }
        };

        $embedder = new OllamaEmbedder('nomic-embed-text', 'http://localhost:11434', $http);
        $vector = $embedder->embed('hello');
        self::assertSame([0.7, 0.6, 0.5], $vector);
    }

    public function testHuggingFaceEmbedderParsesFeatureExtractionResponse(): void
    {
        $http = new class () implements HttpTransportInterface {
            public function postJson(string $url, array $headers, array $jsonBody): array
            {
                return [[0.1, 0.2, 0.3]];
            }
        };

        $embedder = new HuggingFaceEmbedder('sentence-transformers/all-MiniLM-L6-v2', 'test-token', 'https://api-inference.huggingface.co/models', $http);
        $vector = $embedder->embed('hello');
        self::assertSame([0.1, 0.2, 0.3], $vector);
    }

    public function testHuggingFaceEmbedderMeanPoolsTokenEmbeddings(): void
    {
        $http = new class () implements HttpTransportInterface {
            public function postJson(string $url, array $headers, array $jsonBody): array
            {
                return [[[1.0, 0.0], [3.0, 2.0]]];
            }
        };

        $embedder = new HuggingFaceEmbedder('model', http: $http);
        self::assertSame([2.0, 1.0], $embedder->embed('hello'));
    }

    public function testTeiEmbedderMatchesOpenAiShape(): void
    {
        $http = new class () implements HttpTransportInterface {
            public function postJson(string $url, array $headers, array $jsonBody): array
            {
                return ['data' => [['embedding' => [1.0, 0.0]]]];
            }
        };

        $embedder = new TeiEmbedder('bge-small', 'http://127.0.0.1:8080/v1', http: $http);
        self::assertSame([1.0, 0.0], $embedder->embed('a'));
    }
}
