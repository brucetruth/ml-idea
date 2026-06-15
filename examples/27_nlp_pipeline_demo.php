<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\Dataset\Registry\DatasetRegistry;
use ML\IDEA\NLP\Lexicon\WordNetLexicon;
use ML\IDEA\NLP\Sentiment\SentimentAnalyzer;
use ML\IDEA\NLP\Text\NlpPipeline;
use ML\IDEA\NLP\Text\Text;
use ML\IDEA\NLP\Vectorize\HashingEmbeddingProvider;
use ML\IDEA\RAG\QueryExpansion\WordNetQueryExpander;

echo "Example 27 - NLP pipeline + integrations\n";

$integrity = (new DatasetRegistry())->integrityReport();
echo 'Bundled datasets present: ' . count(array_filter($integrity, static fn (array $row): bool => ($row['exists'] ?? false))) . PHP_EOL;

$text = Text::of('Travel to Lusaka was amazing');
$pipeline = $text->pipelineForDetectedLanguage();

echo 'Detected pipeline language route: ' . json_encode($pipeline->nerTagger()::class) . PHP_EOL;
print_r($text->entities(pipeline: $pipeline));

$sentiment = new SentimentAnalyzer();
$sentiment->trainFromBundledDataset();
print_r($text->sentiment(analyzer: $sentiment));

$expander = WordNetQueryExpander::fromLexicon(new WordNetLexicon(), 6);
echo 'WordNet RAG queries: ' . implode(' | ', $expander->expand('happy dog')) . PHP_EOL;

$embedding = (new HashingEmbeddingProvider())->embed($text->value());
echo 'Hashing embedding dims: ' . count($embedding) . PHP_EOL;

echo 'Content words: ' . implode(', ', $text->wordsWithoutStopwords()) . PHP_EOL;
