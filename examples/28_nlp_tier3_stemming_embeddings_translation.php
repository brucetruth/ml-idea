<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\NLP\Normalize\EnglishNormalizer;
use ML\IDEA\NLP\Translate\EnglishBembaTranslator;
use ML\IDEA\NLP\Vectorize\BM25;
use ML\IDEA\NLP\Vectorize\HashingEmbeddingProvider;
use ML\IDEA\RAG\Chains\RetrievalQAChain;
use ML\IDEA\RAG\Document;
use ML\IDEA\RAG\Embeddings\EmbeddingProviderAdapter;
use ML\IDEA\RAG\Embeddings\HashEmbedder;
use ML\IDEA\RAG\LLM\LlmClientFactory;
use ML\IDEA\RAG\Splitters\RecursiveTextSplitter;
use ML\IDEA\RAG\VectorStore\InMemoryVectorStore;

echo "Example 28 - Tier 3: normalization, embeddings bridge, Bemba MT\n";

echo PHP_EOL . "1) English normalization (light stemming for lexical retrieval)\n";
echo 'cats -> ' . EnglishNormalizer::normalize('cats') . PHP_EOL;
echo 'running -> ' . EnglishNormalizer::normalize('running') . PHP_EOL;

$bm25 = new BM25(normalizeEnglish: true);
$bm25->addDocuments(['models are saved and loaded by helpers']);
$bm25->build();
$hits = $bm25->search('model saving', 1);
echo 'BM25 normalized query hit score: ' . round($hits[0]['score'] ?? 0.0, 4) . PHP_EOL;

echo PHP_EOL . "2) Embeddings: NLP HashingEmbeddingProvider vs RAG HashEmbedder\n";
$nlp = (new HashingEmbeddingProvider())->embed('persist model data');
$ragDemo = (new HashEmbedder(64))->embed('persist model data');
$ragBridge = (new EmbeddingProviderAdapter(new HashingEmbeddingProvider()))->embed('persist model data');
echo 'HashingEmbeddingProvider dims: ' . count($nlp) . PHP_EOL;
echo 'HashEmbedder dims: ' . count($ragDemo) . PHP_EOL;
echo 'EmbeddingProviderAdapter dims: ' . count($ragBridge) . PHP_EOL;
echo "Use HashingEmbeddingProvider (+ adapter) when you want NLP tokenization/hashing semantics in RAG.\n";
echo "Use HashEmbedder directly for lightweight deterministic RAG demos.\n";

$chain = new RetrievalQAChain(
    new EmbeddingProviderAdapter(new HashingEmbeddingProvider()),
    new InMemoryVectorStore(),
    new RecursiveTextSplitter(120, 20),
    LlmClientFactory::fromEnv(),
);
$chain->index([new Document('doc-1', 'Model save and load helpers persist trained models.')]);
$result = $chain->ask('How do I save models?', 1);
echo 'RAG via adapter answer prefix: ' . mb_substr($result['answer'], 0, 80) . '...' . PHP_EOL;

echo PHP_EOL . "3) English -> Bemba hybrid translation\n";
$translator = new EnglishBembaTranslator();
$source = 'Thank you and good morning';
$draft = $translator->translate($source);
echo 'Source: ' . $source . PHP_EOL;
echo 'Draft: ' . $draft . PHP_EOL;
echo 'Coverage: ' . round($translator->translationCoverage($source), 3) . PHP_EOL;
echo "Optional: set TRANSLATION_LLM_ENABLED=1 in example 22 for LLM post-edit.\n";
