<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Dataset\Services\GeoDatasetService;
use ML\IDEA\NLP\Detect\LanguageDetector;
use ML\IDEA\NLP\Detect\LanguageRouting;
use ML\IDEA\NLP\Ner\RuleBasedNerTagger;
use ML\IDEA\NLP\Ner\PerceptronNerTagger;
use ML\IDEA\NLP\Pos\RuleBasedPosTagger;
use ML\IDEA\NLP\Pos\PerceptronPosTagger;
use ML\IDEA\Geo\GeoService;
use ML\IDEA\NLP\Rag\Bm25Retriever;
use ML\IDEA\NLP\Rag\Chunker;
use ML\IDEA\NLP\Rag\CitationFormatter;
use ML\IDEA\NLP\Similarity\CosineSimilarity;
use ML\IDEA\NLP\Similarity\JaccardSimilarity;
use ML\IDEA\NLP\Similarity\LevenshteinSimilarity;
use ML\IDEA\NLP\Text\Text;
use ML\IDEA\NLP\Translate\DictionaryTranslator;
use ML\IDEA\NLP\Translate\HybridTranslator;
use ML\IDEA\NLP\Translate\PhraseTableTranslator;
use ML\IDEA\NLP\Vectorize\BM25;
use ML\IDEA\NLP\Vectorize\HashingVectorizer;
use PHPUnit\Framework\TestCase;

final class NlpPhase2Test extends TestCase
{
    public function testLanguageDetectorAndTextExtensions(): void
    {
        $text = Text::of('The quick brown fox jumps over the lazy dog.');
        self::assertSame('en', $text->language());
        self::assertSame('en', $text->languageWithScore()['language']);
        self::assertSame('TQBFJO', $text->initials());
        self::assertNotEmpty($text->keywords(3));
    }

    public function testLanguageDetectorCanAcceptCustomProfile(): void
    {
        $detector = new LanguageDetector();
        $detector->addProfile('sw', [' na' => 0.07, ' wa' => 0.06, ' ku' => 0.05]);
        $this->assertArrayHasKey('sw', $detector->profiles());
    }

    public function testLanguageDetectorIncludesZambiaProfiles(): void
    {
        $profiles = (new LanguageDetector())->profiles();
        self::assertArrayHasKey('bem', $profiles);
        self::assertArrayHasKey('nya', $profiles);
        self::assertArrayHasKey('toi', $profiles);
        self::assertArrayHasKey('loz', $profiles);
    }

    public function testLanguageRoutingProvidesPresetForBemba(): void
    {
        $route = LanguageRouting::forLanguage('bem');
        self::assertSame('unicode_word', $route['tokenizer']);
        self::assertSame('zambia-bemba', $route['nerPreset']);
        self::assertSame('bem->en', $route['translatorDirection']);
    }

    public function testHybridTranslatorPrefersPhraseThenWordAndPreservesPunctuation(): void
    {
        $hybrid = new HybridTranslator(
            new PhraseTableTranslator([
                'above abdomen' => 'pa mulu ifumo',
            ]),
            new DictionaryTranslator([
                'above' => 'pa mulu',
                'abdomen' => 'ifumo',
            ])
        );

        $translated = $hybrid->translate('Above Abdomen!');
        self::assertSame('Pa mulu ifumo!', $translated);
    }

    public function testHashingVectorizerProducesVectors(): void
    {
        $hv = new HashingVectorizer(64);
        $m = $hv->transform(['hello world', 'hello php']);
        self::assertCount(2, $m);
        self::assertCount(64, $m[0]);
    }

    public function testBm25SearchFindsRelevantDoc(): void
    {
        $bm25 = new BM25();
        $bm25->addDocuments(['machine learning in php', 'football match analysis']);
        $bm25->build();

        $hits = $bm25->search('php machine', 1);
        self::assertCount(1, $hits);
        self::assertSame(0, $hits[0]['id']);
    }

    public function testSimilaritiesReturnExpectedRanges(): void
    {
        self::assertGreaterThan(0.9, CosineSimilarity::between([1, 2, 3], [1, 2, 3]));
        self::assertGreaterThan(0.2, JaccardSimilarity::between(['a', 'b'], ['b', 'c']));
        self::assertGreaterThan(0.6, LevenshteinSimilarity::between('kitten', 'sitten'));
    }

    public function testRagHelpersChunkRetrieveAndFormat(): void
    {
        $chunks = (new Chunker())->chunkByWords('one two three four five six seven eight', 3, 1);
        self::assertNotEmpty($chunks);

        $retriever = new Bm25Retriever();
        $retriever->index($chunks);
        $hits = $retriever->retrieve('five six', 2);
        self::assertNotEmpty($hits);

        $formatted = (new CitationFormatter())->format($hits);
        self::assertStringContainsString('[1]', $formatted);
    }

    public function testMultilingualPosAndNer(): void
    {
        $frPos = new RuleBasedPosTagger('fr');
        $tagged = $frPos->tag(Text::of('Je suis rapidement ici')->toTokens());
        self::assertNotEmpty($tagged);

        $ner = (new RuleBasedNerTagger(['paris' => 'LOCATION']))->extract('Alice visited Paris on 2026-02-13');
        self::assertNotEmpty($ner);
        self::assertContains('LOCATION', array_map(static fn($e) => $e->label, $ner));
    }

    public function testRuleBasedNerCatchesExtraPatterns(): void
    {
        $text = 'Ping 192.168.0.1 at 13:45, progress 98%, id 123e4567-e89b-12d3-a456-426614174000 #ops @dev_team';
        $entities = (new RuleBasedNerTagger())->extract($text);
        $labels = array_map(static fn ($e) => $e->label, $entities);

        self::assertContains('IP_ADDRESS', $labels);
        self::assertContains('TIME', $labels);
        self::assertContains('PERCENT', $labels);
        self::assertContains('UUID', $labels);
        self::assertContains('HASHTAG', $labels);
        self::assertContains('MENTION', $labels);
    }

    public function testTrainablePerceptronPosAndNerWorkOnToyData(): void
    {
        $pos = new PerceptronPosTagger();
        $pos->train(
            [['Alice', 'runs'], ['The', 'fox']],
            [['PROPN', 'VERB'], ['DET', 'NOUN']],
            6
        );
        $tagged = $pos->tag(Text::of('Alice runs')->toTokens());
        self::assertNotEmpty($tagged);

        $ner = new PerceptronNerTagger();
        $ner->train(
            [['Alice', 'visited', 'Paris']],
            [['B-PER', 'O', 'B-LOC']],
            8
        );
        $entities = $ner->extract('Alice visited Paris');
        self::assertNotEmpty($entities);
    }

    public function testRuleBasedNerCanUseGeoGazetteer(): void
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'geo_test_' . uniqid('', true);
        mkdir($base . '/geo', 0777, true);
        file_put_contents($base . '/geo/countries+states.json', json_encode([
            ['name' => 'Zambia', 'iso2' => 'ZM', 'iso3' => 'ZMB', 'states' => []],
            ['name' => 'Kenya', 'iso2' => 'KE', 'iso3' => 'KEN', 'states' => []],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($base . '/geo/countries.json', json_encode([
            ['name' => ['common' => 'Zambia'], 'latlng' => [-13.0, 28.0]],
            ['name' => ['common' => 'Kenya'], 'latlng' => [1.0, 38.0]],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($base . '/geo/cities.json', json_encode([
            ['name' => 'Lusaka', 'country_code' => 'ZM', 'state_code' => '', 'latitude' => '-15.4167', 'longitude' => '28.2833'],
            ['name' => 'Nairobi', 'country_code' => 'KE', 'state_code' => '', 'latitude' => '-1.286389', 'longitude' => '36.817223'],
        ], JSON_THROW_ON_ERROR));

        $geo = new GeoService(new GeoDatasetService($base . '/geo'));
        $ner = (new RuleBasedNerTagger())->withGeoGazetteer($geo, 5000);
        $entities = $ner->extract('We flew from Lusaka to Nairobi.');
        self::assertNotEmpty($entities);
    }
}
