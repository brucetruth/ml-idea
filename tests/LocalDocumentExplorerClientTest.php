<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\RAG\LLM\LocalDocumentExplorerClient;
use PHPUnit\Framework\TestCase;

final class LocalDocumentExplorerClientTest extends TestCase
{
    public function testSearchSummaryEntitiesAndGlossaryWork(): void
    {
        $client = new LocalDocumentExplorerClient();
        $client->addSourceText('d1', 'Refund policy allows refunds within 30 days. Email support@example.com for help.');
        $client->addSourceText('d2', 'We operate in Zambia and Kenya with branches in Lusaka and Nairobi.');
        $client->buildIndex(140, 20);

        $search = $client->search('refund policy', 3);
        self::assertNotEmpty($search);

        $summary = $client->summarizeExtractive('summarize refund policy', 2, 4);
        self::assertNotEmpty($summary);

        $countries = $client->generate('list all countries mentioned');
        self::assertStringContainsString('Zambia', $countries);

        $glossary = $client->generate('build glossary from corpus');
        self::assertStringContainsString('Glossary:', $glossary);
    }

    public function testGenerateSearchOutputIncludesCitationsAndRedaction(): void
    {
        $client = new LocalDocumentExplorerClient();
        $client->addSourceText('d', 'Contact support@example.com and review refund policy terms.');
        $client->buildIndex(120, 20);

        $out = $client->generate('show sections about refunds');
        self::assertStringContainsString('Top results:', $out);
        self::assertStringContainsString('[EMAIL]', $out);
    }

    public function testStructuredQueryFiltersAndToolbeltHelpersWork(): void
    {
        $client = new LocalDocumentExplorerClient();
        $client->sourceAdd('d1', 'Refund policy with receipt applies in January only.', ['source' => 'notes', 'tag' => 'policy', 'date' => '2026-01-12']);
        $client->sourceAdd('d2', 'Shipping delays in February for external vendors.', ['source' => 'notes', 'tag' => 'ops', 'date' => '2026-02-10']);
        $client->indexBuild(100, 20);

        $hits = $client->searchBm25('"refund policy" AND receipt NOT shipping source:notes tag:policy date:2026-01..2026-01', topK: 5);
        self::assertNotEmpty($hits);
        self::assertSame('d1', $hits[0]['docId']);

        $doc = $client->fetchDoc('d1');
        self::assertNotNull($doc);
        self::assertStringContainsString('Refund policy', (string) ($doc['text'] ?? ''));

        $snippet = $client->fetchSnippet('d1', 0, 12);
        self::assertSame('Refund polic', $snippet);

        $citations = $client->formatCitations($hits);
        self::assertStringContainsString('d1:', $citations);

        $table = $client->formatTable([['a' => 'x', 'b' => 'y']]);
        self::assertStringContainsString('a | b', $table);
    }

    public function testIndexSaveAndLoadRoundTripWorks(): void
    {
        $client = new LocalDocumentExplorerClient();
        $client->sourceAdd('d1', 'Kenya and Zambia are mentioned here.', ['source' => 'notes']);
        $client->indexBuild(120, 20);

        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mlidea_local_explorer_test_' . uniqid('', true) . '.json';
        $client->indexSave($path);

        $other = new LocalDocumentExplorerClient();
        $other->indexLoad($path);
        @unlink($path);

        $hits = $other->searchBm25('Kenya', topK: 2);
        self::assertNotEmpty($hits);
    }

    public function testStructuredEnvelopeAndNearQueryWork(): void
    {
        $client = new LocalDocumentExplorerClient();
        $client->sourceAdd('d1', 'Refund policy allows refunds within 30 days with receipt.');
        $client->sourceAdd('d2', 'Shipping policy applies after 45 days.');
        $client->indexBuild(120, 20);

        $json = $client->generate('show sections about refunds', ['structured' => true]);
        /** @var array<string,mixed> $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('answer', $decoded);
        self::assertArrayHasKey('citations', $decoded);
        self::assertArrayHasKey('debug', $decoded);

        $hits = $client->searchBm25('near(refund, "30 days", window=40)');
        self::assertNotEmpty($hits);
    }

    public function testIncrementalIndexingMarksDirtyDocs(): void
    {
        $client = new LocalDocumentExplorerClient();
        $client->sourceAdd('d1', 'Lusaka is in Zambia.');
        $client->indexBuild(120, 20);

        $initial = $client->searchBm25('Lusaka', topK: 3);
        self::assertNotEmpty($initial);

        $client->sourceAdd('d1', 'Nairobi is in Kenya.');
        $updated = $client->searchBm25('Nairobi', topK: 3);
        self::assertNotEmpty($updated);
    }

    public function testSplitQuestionsSupportsChainedPrompt(): void
    {
        $client = new LocalDocumentExplorerClient();

        $parts = $client->splitQuestions('where is Lusaka? what is the price of bread there and what time does business start there?');
        self::assertCount(3, $parts);
        self::assertSame('where is Lusaka', $parts[0]);
    }

    public function testQueryMultiResolvesThereAndReturnsNumberedAnswerParts(): void
    {
        $client = new LocalDocumentExplorerClient();
        $client->sourceAdd('geo', 'Lusaka is in Zambia.');
        $client->sourceAdd('biz', 'Bread price in Lusaka is 25 ZMW. Business opening time in Lusaka is 08:00. Contact support@example.com.');
        $client->indexBuild(180, 40);

        $result = $client->queryMulti('where is Lusaka? what is the price of bread there? and what time does business start there?', ['redact_pii' => true]);

        self::assertStringContainsString('1)', (string) ($result['answer'] ?? ''));
        self::assertStringContainsString('2)', (string) ($result['answer'] ?? ''));
        self::assertStringContainsString('3)', (string) ($result['answer'] ?? ''));
        self::assertCount(3, $result['parts'] ?? []);
        self::assertNotEmpty($result['tool_calls'] ?? []);

        $part2 = (string) ($result['parts'][1]['answer'] ?? '');
        self::assertStringContainsString('Lusaka', $part2);

        $allSnippets = array_map(static fn (array $c): string => (string) ($c['snippet'] ?? ''), $result['citations'] ?? []);
        self::assertNotContains('support@example.com', $allSnippets);
    }

    public function testQueryMultiReturnsNotFoundWhenEvidenceMissing(): void
    {
        $client = new LocalDocumentExplorerClient();
        $client->sourceAdd('d1', 'Lusaka is in Zambia.');
        $client->indexBuild(120, 20);

        $result = $client->queryMulti('where is Lusaka? what is the price of jet fuel there?');
        self::assertCount(2, $result['parts'] ?? []);
        self::assertStringContainsString('Not found in local corpus', (string) ($result['parts'][1]['answer'] ?? ''));
    }

    public function testQueryMultiNonGeoUsesGeneralSearchSkill(): void
    {
        $client = new LocalDocumentExplorerClient();
        $client->sourceAdd('d1', 'Refund policy allows refunds within 30 days.');
        $client->indexBuild(120, 20);

        $result = $client->queryMulti('show sections about refunds');
        self::assertStringContainsString('1)', (string) ($result['answer'] ?? ''));
        self::assertContains('SearchSkill', $result['debug']['skills'] ?? []);
        self::assertNotContains('GeoSkill', $result['debug']['skills'] ?? []);
    }

    public function testQueryMultiGeoQueryTriggersGeoSkill(): void
    {
        $client = new LocalDocumentExplorerClient();
        $client->sourceAdd('d1', 'Lusaka is in Zambia.');
        $client->indexBuild(120, 20);

        $result = $client->queryMulti('where is Lusaka?');
        self::assertContains('GeoSkill', $result['debug']['skills'] ?? []);
        $answer = (string) ($result['parts'][0]['answer'] ?? '');
        self::assertTrue(
            str_contains($answer, 'Not found in local corpus/datasets.') || str_contains($answer, 'Lusaka'),
            'Geo result should be either resolved or explicit not found.'
        );
    }

    public function testQueryMultiMixedGeoAndPolicyTriggersBothSkills(): void
    {
        $client = new LocalDocumentExplorerClient();
        $client->sourceAdd('geo', 'Lusaka is in Zambia.');
        $client->sourceAdd('policy', 'Refund policy allows refunds within 30 days with receipt.');
        $client->indexBuild(140, 20);

        $result = $client->queryMulti('where is Lusaka? summarize refund policy.');
        self::assertCount(2, $result['parts'] ?? []);
        self::assertContains('GeoSkill', $result['debug']['skills'] ?? []);
        self::assertContains('PolicySkill', $result['debug']['skills'] ?? []);
    }
}
