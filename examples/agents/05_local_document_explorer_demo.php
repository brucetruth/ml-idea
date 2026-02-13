<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ML\IDEA\RAG\LLM\LocalDocumentExplorerClient;

echo "Agent Demo 05: Local Document Explorer (no LLM, no DB)\n\n";

$client = new LocalDocumentExplorerClient();

$source1 = <<<TXT
Refund policy: Refunds are allowed within 30 days with receipt.
Contact support@example.com or +260 977 123 456 for assistance.
TXT;
$meta1 = ['source' => 'notes', 'tag' => 'policy', 'date' => '2026-01-12'];

$source2 = <<<TXT
We operate in Zambia, Kenya and South Africa.
Branches include Lusaka, Ndola and Nairobi.
TXT;
$meta2 = ['source' => 'notes', 'tag' => 'geo', 'date' => '2026-02-01'];

$artifactDir = __DIR__ . '/../artifacts/doc_explorer_cache';
if (!is_dir($artifactDir)) {
    mkdir($artifactDir, 0777, true);
}

$signature = sha1((string) json_encode([
    'chunkSize' => 180,
    'overlap' => 40,
    'docs' => [
        ['id' => 'note-1', 'text' => $source1, 'meta' => $meta1],
        ['id' => 'note-2', 'text' => $source2, 'meta' => $meta2],
    ],
], JSON_THROW_ON_ERROR));
$indexFile = $artifactDir . '/local_explorer_' . $signature . '.json';

if (is_file($indexFile)) {
    $client->indexLoad($indexFile);
} else {
    $client->sourceAdd('note-1', $source1, $meta1);
    $client->sourceAdd('note-2', $source2, $meta2);
    $client->indexBuild(chunkSize: 180, overlap: 40);
    $client->indexSave($indexFile);
}

echo "1) search\n";
echo $client->generate('show sections about refunds') . PHP_EOL . PHP_EOL;

echo "1a) structured response envelope\n";
echo $client->generate('show sections about refunds', ['structured' => true]) . PHP_EOL . PHP_EOL;

echo "1b) structured search (phrase + boolean + filters)\n";
$hits = $client->searchBm25('"refund policy" AND receipt NOT shipping source:notes tag:policy date:2026-01..2026-01', topK: 3);
echo $client->formatTable(array_map(static fn (array $h): array => [
    'id' => $h['id'],
    'score' => round((float) $h['score'], 4),
    'doc' => $h['docId'],
], $hits)) . PHP_EOL;
echo 'Citations: ' . $client->formatCitations($hits) . PHP_EOL . PHP_EOL;

echo "2) entities\n";
echo $client->generate('list all countries mentioned') . PHP_EOL . PHP_EOL;

echo "3) summary\n";
echo $client->generate('summarize refund policy details with citations') . PHP_EOL . PHP_EOL;

echo "3a) near() query\n";
$nearHits = $client->searchBm25('near(refund, "30 days", window=40) source:notes');
echo $client->formatTable(array_map(static fn (array $h): array => [
    'id' => $h['id'],
    'score' => round((float) $h['score'], 4),
], $nearHits)) . PHP_EOL . PHP_EOL;

echo "3b) fetch snippet\n";
$snippet = $client->fetchSnippet('note-1', 0, 70) ?? '';
echo $client->redactPii($snippet) . PHP_EOL . PHP_EOL;

echo "4) glossary\n";
echo $client->generate('build glossary from corpus') . PHP_EOL;
