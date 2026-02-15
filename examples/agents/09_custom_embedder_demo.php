<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ML\IDEA\RAG\Chains\RetrievalQAChain;
use ML\IDEA\RAG\Contracts\EmbedderInterface;
use ML\IDEA\RAG\Contracts\HttpTransportInterface;
use ML\IDEA\RAG\Contracts\LlmClientInterface;
use ML\IDEA\RAG\Contracts\QueryExpanderInterface;
use ML\IDEA\RAG\Document;
use ML\IDEA\RAG\Http\SimpleHttpTransport;
use ML\IDEA\RAG\LLM\EchoLlmClient;
use ML\IDEA\RAG\Rerankers\LexicalOverlapReranker;
use ML\IDEA\RAG\Splitters\RecursiveTextSplitter;
use ML\IDEA\RAG\VectorStore\InMemoryVectorStore;

/**
 * Example 09:
 * Custom EmbedderInterface + custom QueryExpanderInterface in a RAG chain.
 */
final class KeywordBinaryEmbedder implements EmbedderInterface
{
    /** @param array<int, string> $vocab */
    public function __construct(private readonly array $vocab)
    {
    }

    public function embed(string $text): array
    {
        $t = mb_strtolower($text);
        $vec = [];
        foreach ($this->vocab as $term) {
            $vec[] = str_contains($t, $term) ? 1.0 : 0.0;
        }

        return $vec;
    }

    public function embedBatch(array $texts): array
    {
        $out = [];
        foreach ($texts as $text) {
            $out[] = $this->embed($text);
        }

        return $out;
    }
}

final class SynonymQueryExpander implements QueryExpanderInterface
{
    public function expand(string $query): array
    {
        $q = mb_strtolower($query);
        $expanded = [$q];

        if (str_contains($q, 'persist')) {
            $expanded[] = str_replace('persist', 'save', $q);
            $expanded[] = str_replace('persist', 'serialize', $q);
        }

        if (str_contains($q, 'model')) {
            $expanded[] = str_replace('model', 'classifier', $q);
        }

        return array_values(array_unique($expanded));
    }
}

/**
 * Minimal Claude-style LLM client for RetrievalQAChain.
 *
 * Env example:
 * CLAUDE_API_KEY=... CLAUDE_LLM_MODEL=claude-3-5-sonnet-20240620 php examples/agents/09_custom_embedder_demo.php
 */
final class ClaudeApiLlmClient implements LlmClientInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-3-5-sonnet-20240620',
        private readonly string $baseUrl = 'https://api.anthropic.com/v1',
        private readonly HttpTransportInterface $http = new SimpleHttpTransport(),
    ) {
    }

    public function generate(string $prompt, array $options = []): string
    {
        $response = $this->http->postJson(
            rtrim($this->baseUrl, '/') . '/messages',
            [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
            ],
            [
                'model' => $this->model,
                'max_tokens' => (int) ($options['max_tokens'] ?? 600),
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]
        );

        return (string) ($response['content'][0]['text'] ?? '');
    }
}

$embedder = new KeywordBinaryEmbedder([
    'persist', 'save', 'serialize', 'model', 'classifier', 'vector', 'store', 'tool', 'agent',
]);

$claudeApiKey = (string) (getenv('CLAUDE_API_KEY') ?: '');
$claudeModel = (string) (getenv('CLAUDE_LLM_MODEL') ?: 'claude-3-5-sonnet-20240620');

/** @var LlmClientInterface $llm */
$llm = $claudeApiKey !== ''
    ? new ClaudeApiLlmClient($claudeApiKey, $claudeModel)
    : new EchoLlmClient();

$chain = new RetrievalQAChain(
    $embedder,
    new InMemoryVectorStore(),
    new RecursiveTextSplitter(180, 20),
    $llm,
    new LexicalOverlapReranker(),
    new SynonymQueryExpander(),
);

$chain->index([
    new Document('kb-1', 'Persist models using ModelSerializer::save and load via ModelSerializer::load.'),
    new Document('kb-2', 'Vector stores available include in-memory, JSON, and SQLite backends.'),
    new Document('kb-3', 'ToolRoutingAgent can call tools through local or provider-backed routing models.'),
]);

$query = $argv[1] ?? 'How can I persist a model?';
$result = $chain->ask($query, 2);

echo "Example 09 - Custom embedder + custom query expander\n";
echo 'LLM backend: ' . ($claudeApiKey !== '' ? 'Claude API' : 'EchoLlmClient (set CLAUDE_API_KEY to use real provider)') . PHP_EOL;
echo 'Q: ' . $query . PHP_EOL;
echo 'Answer: ' . $result['answer'] . PHP_EOL;
echo 'Citations: ' . json_encode($result['citations'], JSON_THROW_ON_ERROR) . PHP_EOL;
echo 'Diagnostics: ' . json_encode($result['diagnostics'], JSON_THROW_ON_ERROR) . PHP_EOL;
