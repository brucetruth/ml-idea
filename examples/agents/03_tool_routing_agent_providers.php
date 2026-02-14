<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ML\IDEA\RAG\Agents\ToolRoutingAgent;
use ML\IDEA\RAG\Chains\RetrievalQAChain;
use ML\IDEA\RAG\Document;
use ML\IDEA\RAG\Embeddings\AzureOpenAIEmbedder;
use ML\IDEA\RAG\Embeddings\HashEmbedder;
use ML\IDEA\RAG\LLM\AzureOpenAIToolRoutingModel;
use ML\IDEA\RAG\LLM\EchoLlmClient;
use ML\IDEA\RAG\LLM\OllamaToolRoutingModel;
use ML\IDEA\RAG\LLM\OpenAIToolRoutingModel;
use ML\IDEA\RAG\Rerankers\LexicalOverlapReranker;
use ML\IDEA\RAG\Splitters\RecursiveTextSplitter;
use ML\IDEA\RAG\Tools\FreeApiGetTool;
use ML\IDEA\RAG\Tools\MathTool;
use ML\IDEA\RAG\Tools\RetrievalQaTool;
use ML\IDEA\RAG\Tools\WeatherTool;
use ML\IDEA\RAG\VectorStore\InMemoryVectorStore;

/**
 * Provider select via env:
 * AGENT_PROVIDER=openai|azure|ollama
 */
$provider = getenv('AGENT_PROVIDER') ?: 'openai';

$kbText = (string) file_get_contents(__DIR__ . '/knowledge_base.txt');

$embedder = getenv('AZURE_OPENAI_API_KEY') && getenv('AZURE_OPENAI_ENDPOINT') && getenv('AZURE_OPENAI_EMBED_DEPLOYMENT')
    ? new AzureOpenAIEmbedder(
        (string) getenv('AZURE_OPENAI_API_KEY'),
        (string) getenv('AZURE_OPENAI_ENDPOINT'),
        (string) getenv('AZURE_OPENAI_EMBED_DEPLOYMENT')
    )
    : new HashEmbedder(32);

$chain = new RetrievalQAChain(
    $embedder,
    new InMemoryVectorStore(),
    new RecursiveTextSplitter(220, 40),
    new EchoLlmClient(),
    new LexicalOverlapReranker(),
);
$chain->index([new Document('kb-1', $kbText)]);

$router = match ($provider) {
    'azure' => new AzureOpenAIToolRoutingModel(
        (string) getenv('AZURE_OPENAI_API_KEY'),
        (string) getenv('AZURE_OPENAI_ENDPOINT'),
        (string) getenv('AZURE_OPENAI_CHAT_DEPLOYMENT') //gpt-5-mini
           //api-version: 2024-12-01-preview
    ),
    'ollama' => new OllamaToolRoutingModel(
        (string) (getenv('OLLAMA_MODEL') ?: 'llama3.1')
    ),
    default => new OpenAIToolRoutingModel(
        (string) getenv('OPENAI_API_KEY'),
        (string) (getenv('OPENAI_CHAT_MODEL') ?: 'gpt-4o-mini')
    ),
};

$agent = new ToolRoutingAgent($router, [
    new RetrievalQaTool($chain),
    new MathTool(),
    new WeatherTool(),
    new FreeApiGetTool(['https://jsonplaceholder.typicode.com']),
]);

$query = $argv[1] ?? 'Use tools as needed: what is sqrt(81)+11 and summarize local KB persistence note?';
$result = $agent->chat($query);

echo 'Provider: ' . $provider . PHP_EOL;
echo 'Answer: ' . $result['answer'] . PHP_EOL;
echo 'Tool calls: ' . json_encode($result['tool_calls'], JSON_THROW_ON_ERROR) . PHP_EOL;
