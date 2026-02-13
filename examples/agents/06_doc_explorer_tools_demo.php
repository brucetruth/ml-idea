<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use ML\IDEA\RAG\Agents\ToolCallingAgent;
use ML\IDEA\RAG\Explorer\DocumentExplorerService;
use ML\IDEA\RAG\Tools\CorpusStatsTool;
use ML\IDEA\RAG\Tools\DocSearchTool;
use ML\IDEA\RAG\Tools\DocSummarizeTool;
use ML\IDEA\RAG\Tools\EntityExtractTool;
use ML\IDEA\RAG\Tools\GlossaryTool;
use ML\IDEA\RAG\Tools\RegexExtractTool;

echo "Agent Demo 06: Document Explorer Tools\n\n";

$svc = new DocumentExplorerService();

$doc1 = 'Refund policy allows refunds within 30 days. Contact support@example.com for assistance.';
$doc2 = 'We operate in Zambia, Kenya and South Africa. Branches include Lusaka and Nairobi.';
$meta1 = ['source' => 'notes', 'tag' => 'policy', 'date' => '2026-01-12'];
$meta2 = ['source' => 'notes', 'tag' => 'geo', 'date' => '2026-02-01'];

$artifactDir = __DIR__ . '/../artifacts/doc_explorer_cache';
if (!is_dir($artifactDir)) {
    mkdir($artifactDir, 0777, true);
}

$signature = sha1((string) json_encode([
    'chunkSize' => 180,
    'overlap' => 40,
    'docs' => [
        ['id' => 'note-1', 'text' => $doc1, 'meta' => $meta1],
        ['id' => 'note-2', 'text' => $doc2, 'meta' => $meta2],
    ],
], JSON_THROW_ON_ERROR));
$indexFile = $artifactDir . '/doc_explorer_tools_' . $signature . '.json';

if (is_file($indexFile)) {
    $svc->loadIndex($indexFile);
} else {
    $svc->addSourceText('note-1', $doc1, $meta1);
    $svc->addSourceText('note-2', $doc2, $meta2);
    $svc->buildIndex(180, 40);
    $svc->saveIndex($indexFile);
}

$agent = new ToolCallingAgent([
    new DocSearchTool($svc),
    new DocSummarizeTool($svc),
    new RegexExtractTool($svc),
    new EntityExtractTool($svc),
    new CorpusStatsTool($svc),
    new GlossaryTool($svc),
]);

echo "1) doc_search\n";
echo $agent->run('tool:doc_search {"query":"refund AND receipt source:notes","topK":3}') . PHP_EOL . PHP_EOL;

echo "1a) doc_search (standard envelope)\n";
echo $agent->run('tool:doc_search {"query":"show sections about refunds","envelope":true,"topK":3}') . PHP_EOL . PHP_EOL;

echo "2) doc_summarize\n";
echo $agent->run('tool:doc_summarize {"query":"summarize refund policy","maxSentences":2}') . PHP_EOL . PHP_EOL;

echo "2a) doc_summarize (standard envelope)\n";
echo $agent->run('tool:doc_summarize {"query":"summarize refund policy","envelope":true}') . PHP_EOL . PHP_EOL;

echo "3) regex_extract\n";
echo $agent->run('tool:regex_extract {"pattern":"/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+[.][A-Za-z]{2,}/","limit":5}') . PHP_EOL . PHP_EOL;

echo "4) entity_extract\n";
echo $agent->run('tool:entity_extract {"types":["COUNTRY"]}') . PHP_EOL . PHP_EOL;

echo "5) corpus_stats\n";
echo $agent->run('tool:corpus_stats {}') . PHP_EOL . PHP_EOL;

echo "6) glossary\n";
echo $agent->run('tool:glossary {"topN":10}') . PHP_EOL;
