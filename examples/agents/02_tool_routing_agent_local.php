<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ML\IDEA\RAG\Agents\ToolRoutingAgent;
use ML\IDEA\RAG\Chains\RetrievalQAChain;
use ML\IDEA\RAG\Document;
use ML\IDEA\RAG\Embeddings\HashEmbedder;
use ML\IDEA\RAG\LLM\LlmClientFactory;
use ML\IDEA\RAG\LLM\AzureOpenAIToolRoutingModel;
use ML\IDEA\RAG\LLM\HeuristicToolRoutingModel;
use ML\IDEA\RAG\LLM\OllamaToolRoutingModel;
use ML\IDEA\RAG\LLM\OpenAIToolRoutingModel;
use ML\IDEA\RAG\Rerankers\LexicalOverlapReranker;
use ML\IDEA\RAG\Splitters\RecursiveTextSplitter;
use ML\IDEA\RAG\Tools\MathTool;
use ML\IDEA\RAG\Tools\RetrievalQaTool;
use ML\IDEA\RAG\Tools\WeatherTool;
use ML\IDEA\RAG\VectorStore\InMemoryVectorStore;

$kbText = (string) file_get_contents(__DIR__ . '/knowledge_base.txt');

// Local defaults (deterministic):
// - Tool routing: HeuristicToolRoutingModel
// - QA LLM: LlmClientFactory::fromEnv() (defaults to Echo)
//
// Provider-backed routing alternatives (drop-in):
// $router = new OpenAIToolRoutingModel((string) getenv('OPENAI_API_KEY'), (string) (getenv('OPENAI_CHAT_MODEL') ?: 'gpt-4o-mini'));
// $router = new AzureOpenAIToolRoutingModel((string) getenv('AZURE_OPENAI_API_KEY'), (string) getenv('AZURE_OPENAI_ENDPOINT'), (string) getenv('AZURE_OPENAI_CHAT_DEPLOYMENT'));
// $router = new OllamaToolRoutingModel((string) (getenv('OLLAMA_MODEL') ?: 'llama3.1'));
//
// RetrievalQAChain LLM selection:
// Set RAG_LLM_PROVIDER=openai|azure|ollama|echo (plus relevant env vars)
// or pass your own custom LlmClientInterface implementation.

$chain = new RetrievalQAChain(
    new HashEmbedder(24),
    new InMemoryVectorStore(),
    new RecursiveTextSplitter(160, 30),
    LlmClientFactory::fromEnv(),
    new LexicalOverlapReranker(),
);
$chain->index([new Document('kb-1', $kbText)]);

$agent = new ToolRoutingAgent(
    new HeuristicToolRoutingModel(), // replace with $router from the provider-backed options above if desired
    [new RetrievalQaTool($chain), new WeatherTool(), new MathTool()]
);

$q1 = 'What does this library say about model persistence?';
$a1 = $agent->chat($q1);
echo "Q1: {$q1}\nA1: {$a1['answer']}\n\n";

$q2 = 'Please compute sqrt(144) + 3^2';
$a2 = $agent->chat($q2);
echo "Q2: {$q2}\nA2: {$a2['answer']}\n\n";

$q3 = 'What is the weather now in Lusaka?';
$a3 = $agent->chat($q3);
echo "Q3: {$q3}\nA3: {$a3['answer']}\n";
