<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\NLP\Normalize\EnglishNormalizer;
use ML\IDEA\NLP\TfidfVectorizer;
use ML\IDEA\NLP\Translate\BembaPhraseComposer;
use ML\IDEA\NLP\Translate\EnglishBembaTranslator;
use ML\IDEA\NLP\Translate\HybridTranslator;
use ML\IDEA\NLP\Vectorize\BM25;
use ML\IDEA\NLP\Vectorize\HashingEmbeddingProvider;
use ML\IDEA\RAG\Embeddings\EmbeddingProviderAdapter;
use ML\IDEA\RAG\Embeddings\HashEmbedder;
use PHPUnit\Framework\TestCase;

final class NlpTier3Test extends TestCase
{
    public function testEnglishNormalizerCollapsesPluralAndTenseSuffixes(): void
    {
        self::assertSame('cat', EnglishNormalizer::normalize('cats'));
        self::assertSame('run', EnglishNormalizer::normalize('running'));
        self::assertSame('model', EnglishNormalizer::normalize('models'));
        self::assertSame('body', EnglishNormalizer::normalize('bodies'));
    }

    public function testBm25NormalizationImprovesStemMatching(): void
    {
        $plain = new BM25();
        $plain->addDocuments(['the cats run quickly']);
        $plain->build();
        $plainHits = $plain->search('cat running', 1);

        $normalized = new BM25(normalizeEnglish: true);
        $normalized->addDocuments(['the cats run quickly']);
        $normalized->build();
        $normHits = $normalized->search('cat running', 1);

        self::assertSame(0.0, $plainHits[0]['score'] ?? 0.0);
        self::assertGreaterThan(0.0, $normHits[0]['score'] ?? 0.0);
    }

    public function testTfidfVectorizerCanNormalizeEnglishTokens(): void
    {
        $vectorizer = new TfidfVectorizer(normalizeEnglish: true);
        $matrix = $vectorizer->fitTransform(['running models', 'model run']);
        $vocab = $vectorizer->getVocabulary();

        self::assertArrayHasKey('run', $vocab);
        self::assertArrayHasKey('model', $vocab);
        self::assertGreaterThan(0.0, $matrix[0][0] + $matrix[1][0]);
    }

    public function testEmbeddingProviderAdapterMatchesNlpAndRagInterfaces(): void
    {
        $provider = new HashingEmbeddingProvider();
        $adapter = new EmbeddingProviderAdapter($provider);

        $nlpVector = $provider->embed('hello world');
        $ragVector = $adapter->embed('hello world');
        $batch = $adapter->embedBatch(['hello world']);

        self::assertSame($nlpVector, $ragVector);
        self::assertCount(1, $batch);
        self::assertNotSame([], $ragVector);
        self::assertNotSame([], (new HashEmbedder(64))->embed('hello world'));
    }

    public function testBembaPhraseComposerBuildsFromWordMap(): void
    {
        $phrases = BembaPhraseComposer::compose(
            ['above' => 'pa mulu', 'abdomen' => 'ifumo'],
            ['above abdomen'],
        );

        self::assertSame(['above abdomen' => 'pa mulu ifumo'], $phrases);
    }

    public function testEnglishBembaTranslatorUsesSupplementalPhrasesAndReportsCoverage(): void
    {
        $translator = new EnglishBembaTranslator([
            'above' => ['pa mulu'],
            'abdomen' => ['ifumo'],
            'thank' => ['natotela'],
            'you' => ['mwe'],
        ]);

        $translated = $translator->translate('Thank you');
        self::assertNotSame('Thank you', $translated);
        self::assertGreaterThan(0.0, $translator->translationCoverage('Thank you'));
    }

    public function testHybridTranslatorCoverageDetectsPartialTranslation(): void
    {
        $hybrid = new HybridTranslator(
            new \ML\IDEA\NLP\Translate\PhraseTableTranslator(['good morning' => 'mwabuka butu']),
            new \ML\IDEA\NLP\Translate\DictionaryTranslator(['friend' => 'cibwe']),
        );

        $source = 'good morning there';
        $translated = $hybrid->translate($source);
        $coverage = $hybrid->translationCoverage($source, $translated);

        self::assertGreaterThan(0.3, $coverage);
        self::assertLessThan(1.0, $coverage);
    }
}
