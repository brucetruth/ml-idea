<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Embeddings;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\RAG\Contracts\EmbedderInterface;

/** Build text embedders from environment (mirrors LlmClientFactory). */
final class EmbedderFactory
{
    /**
     * Supported via $provider or env RAG_EMBEDDER_PROVIDER:
     * hash (default), openai, azure, ollama, huggingface, tei
     */
    public static function fromEnv(?string $provider = null): EmbedderInterface
    {
        $p = strtolower(trim((string) ($provider ?? getenv('RAG_EMBEDDER_PROVIDER') ?: 'hash')));

        return match ($p) {
            'openai' => new OpenAIEmbedder(
                (string) getenv('OPENAI_API_KEY'),
                (string) (getenv('OPENAI_EMBED_MODEL') ?: 'text-embedding-3-small'),
            ),
            'azure' => new AzureOpenAIEmbedder(
                (string) getenv('AZURE_OPENAI_API_KEY'),
                (string) getenv('AZURE_OPENAI_ENDPOINT'),
                (string) (getenv('AZURE_OPENAI_EMBED_DEPLOYMENT') ?: getenv('AZURE_OPENAI_EMBEDDING_DEPLOYMENT') ?: 'embeddings'),
                (string) (getenv('AZURE_OPENAI_API_VERSION') ?: '2024-02-15-preview'),
            ),
            'ollama' => new OllamaEmbedder(
                (string) (getenv('OLLAMA_EMBED_MODEL') ?: getenv('OLLAMA_MODEL') ?: 'nomic-embed-text'),
                (string) (getenv('OLLAMA_BASE_URL') ?: 'http://localhost:11434'),
            ),
            'huggingface', 'hf' => new HuggingFaceEmbedder(
                (string) (getenv('HF_EMBED_MODEL') ?: 'sentence-transformers/all-MiniLM-L6-v2'),
                (string) (getenv('HF_API_TOKEN') ?: ''),
                (string) (getenv('HF_API_URL') ?: 'https://api-inference.huggingface.co/models'),
            ),
            'tei' => new TeiEmbedder(
                (string) (getenv('TEI_EMBED_MODEL') ?: 'local-model'),
                (string) (getenv('TEI_BASE_URL') ?: 'http://localhost:8080/v1'),
                getenv('TEI_API_KEY') !== false ? (string) getenv('TEI_API_KEY') : null,
            ),
            'hash' => new HashEmbedder((int) (getenv('RAG_HASH_EMBED_DIMS') ?: 256)),
            default => throw new InvalidArgumentException(sprintf('Unknown RAG embedder provider: %s', $p)),
        };
    }
}
