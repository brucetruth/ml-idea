<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\RAG\Agents\ToolCallingAgent;
use ML\IDEA\RAG\Chains\RetrievalQAChain;
use ML\IDEA\RAG\Document;
use ML\IDEA\RAG\Embeddings\HashEmbedder;
use ML\IDEA\RAG\LLM\EchoLlmClient;
use ML\IDEA\RAG\Persistence\VectorIndexPersistence;
use ML\IDEA\RAG\QueryExpansion\SimpleQueryExpander;
use ML\IDEA\RAG\Rerankers\LexicalOverlapReranker;
use ML\IDEA\RAG\Retriever\HybridRetriever;
use ML\IDEA\RAG\Splitters\RecursiveTextSplitter;
use ML\IDEA\RAG\Tools\RetrievalQaTool;
use ML\IDEA\RAG\VectorStore\InMemoryVectorStore;
use PHPUnit\Framework\TestCase;

final class RagAdvancedTest extends TestCase
{
    public function testHybridRetrieverReturnsHits(): void
    {
        $embedder = new HashEmbedder(16);
        $store = new InMemoryVectorStore();

        $items = [
            ['id' => 'a', 'vector' => $embedder->embed('model persistence save load'), 'text' => 'model persistence save load', 'metadata' => []],
            ['id' => 'b', 'vector' => $embedder->embed('cross validation metrics'), 'text' => 'cross validation metrics', 'metadata' => []],
        ];
        $store->upsert($items);

        $retriever = new HybridRetriever($embedder, $store);
        $hits = $retriever->retrieve('how to save model', 1);

        self::assertCount(1, $hits);
    }

    public function testVectorIndexPersistenceRoundTrip(): void
    {
        $store = new InMemoryVectorStore();
        $store->upsert([
            ['id' => 'x', 'vector' => [0.1, 0.2], 'text' => 'hello', 'metadata' => ['m' => 1]],
        ]);

        $path = sys_get_temp_dir() . '/ml_idea_rag_index_' . uniqid('', true) . '.json';
        VectorIndexPersistence::save($store, $path);

        $loaded = new InMemoryVectorStore();
        VectorIndexPersistence::load($loaded, $path);
        @unlink($path);

        $hits = $loaded->search([0.1, 0.2], 1);
        self::assertCount(1, $hits);
        self::assertSame('x', $hits[0]['id']);
    }

    public function testChainReturnsCitationsDiagnosticsAndStream(): void
    {
        $chain = new RetrievalQAChain(
            new HashEmbedder(16),
            new InMemoryVectorStore(),
            new RecursiveTextSplitter(80, 10),
            new EchoLlmClient(),
            new LexicalOverlapReranker(),
            new SimpleQueryExpander(2),
        );

        $chain->index([
            new Document('doc-a', 'ModelSerializer handles save and load.'),
            new Document('doc-b', 'RAG chain supports retrieval and generation.'),
        ]);

        $result = $chain->ask('how to save and load model', 2);
        self::assertArrayHasKey('citations', $result);
        self::assertArrayHasKey('diagnostics', $result);
        self::assertArrayHasKey('verification', $result);

        $parts = iterator_to_array($chain->askStream('how to save and load model', 2));
        self::assertNotEmpty($parts);
    }

    public function testToolCallingAgentCanInvokeRagQaTool(): void
    {
        $chain = new RetrievalQAChain(
            new HashEmbedder(16),
            new InMemoryVectorStore(),
            new RecursiveTextSplitter(80, 10),
            new EchoLlmClient(),
        );
        $chain->index([new Document('d', 'Model persistence uses save and load.')]);

        $tool = new RetrievalQaTool($chain);
        $agent = new ToolCallingAgent([$tool]);

        $response = $agent->run('tool:rag_qa {"question":"How do I save models?","k":1}');
        self::assertNotSame('', trim($response));
    }

    public function testToolCallingAgentSupportsCustomAgentPromptFields(): void
    {
        $agent = new ToolCallingAgent(
            [],
            agentName: 'LocalToolRunner',
            agentFeatures: ['Executes only explicit tool protocol calls']
        );

        $prompt = $agent->getSystemPrompt();
        self::assertStringContainsString('You are LocalToolRunner.', $prompt);
        self::assertStringContainsString('Executes only explicit tool protocol calls', $prompt);
        self::assertStringContainsString('tool:TOOL_NAME', $agent->getInvocationGuide());
    }
}
