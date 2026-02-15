<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ML\IDEA\RAG\Chains\RetrievalQAChain;
use ML\IDEA\RAG\Contracts\LlmClientInterface;
use ML\IDEA\RAG\Document;
use ML\IDEA\RAG\Embeddings\HashEmbedder;
use ML\IDEA\RAG\Rerankers\LexicalOverlapReranker;
use ML\IDEA\RAG\Splitters\RecursiveTextSplitter;
use ML\IDEA\RAG\VectorStore\InMemoryVectorStore;

/**
 * Example 10 (agents folder):
 * Minimal custom LLM client implementation for RetrievalQAChain.
 */
final class PrefixTemplateLlmClient implements LlmClientInterface
{
    public function __construct(private readonly string $prefix = 'CUSTOM')
    {
    }

    public function generate(string $prompt, array $options = []): string
    {
        $max = isset($options['max_chars']) ? max(60, (int) $options['max_chars']) : 260;
        return $this->prefix . ': ' . mb_substr(trim($prompt), 0, $max);
    }
}

$chain = new RetrievalQAChain(
    new HashEmbedder(24),
    new InMemoryVectorStore(),
    new RecursiveTextSplitter(160, 20),
    new PrefixTemplateLlmClient('MY_APP_LLM'),
    new LexicalOverlapReranker(),
);

$chain->index([
    new Document('doc-1', 'Model persistence is handled by ModelSerializer save/load methods.'),
    new Document('doc-2', 'RAG chains can plug in any LlmClientInterface implementation.'),
]);

$query = $argv[1] ?? 'How can I persist models here?';
$result = $chain->ask($query, 2);

echo "Example 10 - Custom LLM client\n";
echo 'Q: ' . $query . PHP_EOL;
echo 'Answer: ' . $result['answer'] . PHP_EOL;
echo 'Citations: ' . json_encode($result['citations'], JSON_THROW_ON_ERROR) . PHP_EOL;
