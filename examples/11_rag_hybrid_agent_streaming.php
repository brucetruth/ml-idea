<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\RAG\Agents\ToolCallingAgent;
use ML\IDEA\RAG\Chains\RetrievalQAChain;
use ML\IDEA\RAG\Document;
use ML\IDEA\RAG\Embeddings\HashEmbedder;
use ML\IDEA\RAG\LLM\EchoLlmClient;
use ML\IDEA\RAG\QueryExpansion\SimpleQueryExpander;
use ML\IDEA\RAG\Rerankers\LexicalOverlapReranker;
use ML\IDEA\RAG\Splitters\RecursiveTextSplitter;
use ML\IDEA\RAG\Tools\RetrievalQaTool;
use ML\IDEA\RAG\VectorStore\InMemoryVectorStore;

$chain = new RetrievalQAChain(
    new HashEmbedder(24),
    new InMemoryVectorStore(),
    new RecursiveTextSplitter(120, 20),
    new EchoLlmClient(),
    new LexicalOverlapReranker(),
    new SimpleQueryExpander(3),
);

$chain->index([
    new Document('doc-1', 'Hybrid retrieval combines dense embeddings and lexical relevance.'),
    new Document('doc-2', 'Chunk diagnostics include score traces and citation IDs.'),
    new Document('doc-3', 'Tool-calling lets an agent invoke RAG QA with structured inputs.'),
]);

$result = $chain->ask('How does hybrid retrieval work?', 2);

echo "Example 11 - Hybrid + Agent + Streaming\n";
echo 'Citations: ' . json_encode($result['citations'], JSON_THROW_ON_ERROR) . PHP_EOL;
echo 'Diagnostics: ' . json_encode($result['diagnostics'], JSON_THROW_ON_ERROR) . PHP_EOL;
echo 'Verification: ' . json_encode($result['verification'], JSON_THROW_ON_ERROR) . PHP_EOL;

echo "Streamed answer chunks:\n";
foreach ($chain->askStream('Explain chunk diagnostics', 2) as $chunk) {
    echo $chunk;
}
echo PHP_EOL;

$agent = new ToolCallingAgent([new RetrievalQaTool($chain)]);
$agentOut = $agent->run('tool:rag_qa {"question":"What are citations in this pipeline?","k":2}');
echo 'Agent tool output: ' . $agentOut . PHP_EOL;
