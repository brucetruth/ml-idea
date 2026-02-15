<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\LLM;

use ML\IDEA\RAG\Contracts\LlmClientInterface;

final class LlmClientFactory
{
    /**
     * Build an LLM client from environment variables.
     *
     * Supported providers via $provider (or env RAG_LLM_PROVIDER):
     * - echo   (default)
     * - openai
     * - azure
     * - ollama
     */
    public static function fromEnv(?string $provider = null): LlmClientInterface
    {
        $p = strtolower(trim((string) ($provider ?? getenv('RAG_LLM_PROVIDER') ?: 'echo')));

        return match ($p) {
            'openai' => new OpenAILlmClient(
                (string) getenv('OPENAI_API_KEY'),
                (string) (getenv('OPENAI_CHAT_MODEL') ?: 'gpt-4o-mini')
            ),
            'azure' => new AzureOpenAILlmClient(
                (string) getenv('AZURE_OPENAI_API_KEY'),
                (string) getenv('AZURE_OPENAI_ENDPOINT'),
                (string) getenv('AZURE_OPENAI_CHAT_DEPLOYMENT'),
                (string) (getenv('AZURE_OPENAI_API_VERSION') ?: '2024-02-15-preview')
            ),
            'ollama' => new OllamaLlmClient(
                (string) (getenv('OLLAMA_MODEL') ?: 'llama3.1'),
                (string) (getenv('OLLAMA_BASE_URL') ?: 'http://localhost:11434')
            ),
            default => new EchoLlmClient(),
        };
    }
}
