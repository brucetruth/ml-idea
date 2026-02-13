<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\RAG\Explorer\DocumentExplorerService;
use ML\IDEA\RAG\LLM\LocalExplorerLlmClientAdapter;
use ML\IDEA\RAG\Tools\CorpusStatsTool;
use ML\IDEA\RAG\Tools\DocSearchTool;
use ML\IDEA\RAG\Tools\DocSummarizeTool;
use ML\IDEA\RAG\Tools\EntityExtractTool;
use ML\IDEA\RAG\Tools\GlossaryTool;
use ML\IDEA\RAG\Tools\RegexExtractTool;
use PHPUnit\Framework\TestCase;

final class RagDocumentExplorerToolsTest extends TestCase
{
    public function testDocumentExplorerToolsReturnStructuredPayloads(): void
    {
        $svc = new DocumentExplorerService();
        $svc->addSourceText('d1', 'Refund policy allows refunds within 30 days. Contact support@example.com.');
        $svc->addSourceText('d2', 'We operate in Zambia and Kenya.');
        $svc->buildIndex(140, 20);

        $search = new DocSearchTool($svc);
        $summary = new DocSummarizeTool($svc);
        $regex = new RegexExtractTool($svc);
        $entity = new EntityExtractTool($svc);
        $stats = new CorpusStatsTool($svc);
        $glossary = new GlossaryTool($svc);

        $searchOut = json_decode($search->invoke(['query' => 'refund policy']), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue((bool) ($searchOut['ok'] ?? false));
        self::assertNotEmpty($searchOut['hits'] ?? []);

        $searchEnvelope = json_decode($search->invoke(['query' => 'show sections about refunds', 'envelope' => true]), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('answer', $searchEnvelope);
        self::assertArrayHasKey('citations', $searchEnvelope);
        self::assertArrayHasKey('debug', $searchEnvelope);

        $summaryOut = json_decode($summary->invoke(['query' => 'summarize refund policy']), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue((bool) ($summaryOut['ok'] ?? false));
        self::assertNotEmpty($summaryOut['summary'] ?? []);

        $summaryEnvelope = json_decode($summary->invoke(['query' => 'summarize refund policy', 'envelope' => true]), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('answer', $summaryEnvelope);
        self::assertArrayHasKey('citations', $summaryEnvelope);
        self::assertArrayHasKey('debug', $summaryEnvelope);

        $regexOut = json_decode($regex->invoke(['pattern' => '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/']), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue((bool) ($regexOut['ok'] ?? false));
        self::assertNotEmpty($regexOut['matches'] ?? []);

        $entityOut = json_decode($entity->invoke(['types' => ['COUNTRY']]), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue((bool) ($entityOut['ok'] ?? false));

        $statsOut = json_decode($stats->invoke([]), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue((bool) ($statsOut['ok'] ?? false));
        self::assertArrayHasKey('stats', $statsOut);

        $glossaryOut = json_decode($glossary->invoke(['topN' => 8]), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue((bool) ($glossaryOut['ok'] ?? false));
        self::assertStringContainsString('Glossary:', (string) ($glossaryOut['glossary'] ?? ''));
    }

    public function testLocalExplorerAdapterImplementsGenerateAndStream(): void
    {
        $svc = new DocumentExplorerService();
        $svc->addSourceText('d1', 'Refund policy allows refunds within 30 days.');
        $svc->buildIndex(140, 20);

        $adapter = new LocalExplorerLlmClientAdapter($svc);
        $text = $adapter->generate('show sections about refunds');
        self::assertNotSame('', trim($text));

        $chunks = iterator_to_array($adapter->streamGenerate('show sections about refunds'));
        self::assertNotEmpty($chunks);
    }
}
