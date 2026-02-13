<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\RAG\Chains\RetrievalQAChain;
use ML\IDEA\RAG\Embeddings\HashEmbedder;
use ML\IDEA\RAG\LLM\EchoLlmClient;
use ML\IDEA\RAG\Loaders\PdoLoader;
use ML\IDEA\RAG\QueryExpansion\SimpleQueryExpander;
use ML\IDEA\RAG\Rerankers\LexicalOverlapReranker;
use ML\IDEA\RAG\Splitters\RecursiveTextSplitter;
use ML\IDEA\RAG\VectorStore\InMemoryVectorStore;

$dbPath = __DIR__ . '/artifacts/rag_docs.sqlite';
if (!is_dir(dirname($dbPath))) {
    mkdir(dirname($dbPath), 0777, true);
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE IF NOT EXISTS docs (id TEXT PRIMARY KEY, text TEXT NOT NULL, topic TEXT, source TEXT)');
$pdo->exec("DELETE FROM docs");

$rows = [
    ['id' => 'd1', 'text' => 'ModelSerializer handles model save and load operations.', 'topic' => 'persistence', 'source' => 'sqlite'],
    ['id' => 'd2', 'text' => 'Hybrid retrieval blends lexical and dense similarity.', 'topic' => 'retrieval', 'source' => 'sqlite'],
    ['id' => 'd3', 'text' => 'Tool-calling agents can invoke rag_qa and other tools.', 'topic' => 'agent', 'source' => 'sqlite'],
];

$stmt = $pdo->prepare('INSERT INTO docs(id, text, topic, source) VALUES (:id, :text, :topic, :source)');
foreach ($rows as $row) {
    $stmt->execute($row);
}

$loader = new PdoLoader(
    $pdo,
    'SELECT id, text, topic, source FROM docs WHERE topic != :excluded',
    textField: 'text',
    idField: 'id',
    params: [':excluded' => 'ignore'],
    metadataFields: ['topic', 'source']
);

$chain = new RetrievalQAChain(
    new HashEmbedder(24),
    new InMemoryVectorStore(),
    new RecursiveTextSplitter(120, 20),
    new EchoLlmClient(),
    new LexicalOverlapReranker(),
    new SimpleQueryExpander(2),
);

$chain->index($loader->load());
$result = $chain->ask('How do we save and load models?', 2);

echo "Example 12 - RAG DB Loader (SQLite/PDO)\n";
echo 'Answer: ' . $result['answer'] . PHP_EOL;
echo 'Citations: ' . json_encode($result['citations'], JSON_THROW_ON_ERROR) . PHP_EOL;
