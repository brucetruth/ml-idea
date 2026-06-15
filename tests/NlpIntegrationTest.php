<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Dataset\Registry\DatasetPaths;
use ML\IDEA\Dataset\Registry\DatasetRegistry;
use ML\IDEA\NLP\Detect\LanguagePipelineFactory;
use ML\IDEA\NLP\Lexicon\WordNetLexicon;
use ML\IDEA\NLP\Pos\PerceptronPosTagger;
use ML\IDEA\NLP\Rag\Chunker;
use ML\IDEA\NLP\Sentiment\SentimentAnalyzer;
use ML\IDEA\NLP\Support\UnicodeStringDistance;
use ML\IDEA\NLP\Text\NlpPipeline;
use ML\IDEA\NLP\Text\Text;
use ML\IDEA\NLP\TfidfVectorizer;
use ML\IDEA\NLP\Vectorize\HashingEmbeddingProvider;
use ML\IDEA\RAG\QueryExpansion\WordNetQueryExpander;
use PHPUnit\Framework\TestCase;

final class NlpIntegrationTest extends TestCase
{
    public function testDatasetPathsPreferFullDatasetExports(): void
    {
        $wordnetPath = DatasetPaths::resolve('wordnet/wn.json');
        self::assertStringContainsString('/Dataset/wordnet/wn.json', $wordnetPath);
        self::assertGreaterThan(100_000, (int) filesize($wordnetPath));

        $integrity = (new DatasetRegistry())->integrityReport();
        self::assertTrue($integrity['wordnet']['exists'] ?? false);
        self::assertTrue($integrity['geo-cities']['exists'] ?? false);
    }

    public function testWordNetQueryExpanderForRag(): void
    {
        $seedPath = dirname(__DIR__) . '/src/datasets/wordnet/wn.json';
        $expander = WordNetQueryExpander::fromLexicon(new WordNetLexicon($seedPath));
        $queries = $expander->expand('happy dog');

        self::assertContains('happy dog', $queries);
        self::assertGreaterThan(1, count($queries));
        foreach ($queries as $query) {
            self::assertGreaterThanOrEqual(2, count(preg_split('/\s+/u', trim($query)) ?: []));
        }
    }

    public function testLanguagePipelineFactoryBuildsComponents(): void
    {
        $pipeline = LanguagePipelineFactory::forLanguage('bem');

        self::assertSame('unicode_word', $pipeline['routing']['tokenizer']);
        self::assertSame('bem', $pipeline['language']);
        self::assertNotNull($pipeline['translator']);
    }

    public function testHashingEmbeddingProviderReturnsVector(): void
    {
        $embedding = (new HashingEmbeddingProvider())->embed('hello world');
        self::assertNotEmpty($embedding);
        self::assertSame(1024, count($embedding));
    }

    public function testTfidfVectorizerUsesSharedTokenizer(): void
    {
        $vectorizer = new TfidfVectorizer(removeStopwords: true);
        $matrix = $vectorizer->fitTransform(['the happy dog runs', 'sad cat sleeps']);

        self::assertNotEmpty($vectorizer->getVocabulary());
        self::assertArrayNotHasKey('the', $vectorizer->getVocabulary());
        self::assertCount(2, $matrix);
    }

    public function testChunkerUsesUnicodeTokenizer(): void
    {
        $chunks = (new Chunker())->chunkByWords('café résumé naïve', 2, 0);
        self::assertSame(['café résumé', 'naïve'], $chunks);
    }

    public function testTextApiPipelineExtensions(): void
    {
        $pipeline = NlpPipeline::forLanguage('en');
        $sentiment = new SentimentAnalyzer();
        $sentiment->train(['great product', 'terrible product'], ['positive', 'negative']);

        $pipeline = new NlpPipeline(sentimentAnalyzer: $sentiment);
        $result = Text::of('great product')->sentiment(pipeline: $pipeline);

        self::assertSame('positive', $result['label']);
        self::assertArrayHasKey('neutral', $result);
    }

    public function testRakeKeywordsUsesSharedStopwords(): void
    {
        $keywords = (new \ML\IDEA\NLP\Extract\RakeKeywords())->extract('the model persistence library', 5);
        $terms = array_map(static fn (array $row): string => $row['keyword'], $keywords);

        self::assertNotContains('the', $terms);
        self::assertTrue(
            count(array_filter($terms, static fn (string $k): bool => str_contains($k, 'model') || str_contains($k, 'persistence'))) >= 1,
        );
    }

    public function testSentimentAnalyzerTrainsThreeClassesFromBundledDataset(): void
    {
        $sentiment = new SentimentAnalyzer();
        $sentiment->trainFromBundledDataset(120);

        $neutral = $sentiment->predict('okay average nothing special');
        $positive = $sentiment->predict('amazing brilliant delightful product');

        self::assertContains($neutral, ['negative', 'neutral', 'positive']);
        self::assertSame('positive', $positive);

        $proba = $sentiment->predictProba('fine I guess neither good nor bad');
        self::assertGreaterThan(0.0, $proba['neutral']);
    }

    public function testPerceptronPersistenceRoundTrip(): void
    {
        $tagger = new PerceptronPosTagger();
        $tagger->train([['Hello', 'world']], [['INTJ', 'NOUN']], epochs: 2);
        $json = $tagger->toJson();

        $loaded = new PerceptronPosTagger();
        $loaded->loadJson($json);
        $tokens = Text::of('Hello world')->toTokens();
        $original = $tagger->tag($tokens);
        $restored = $loaded->tag($tokens);

        self::assertSame(array_column($original, 'pos'), array_column($restored, 'pos'));
    }

    public function testUnicodeStringDistanceHandlesMultibyte(): void
    {
        self::assertGreaterThan(0.5, UnicodeStringDistance::similarity('café', 'cafe'));
    }
}
