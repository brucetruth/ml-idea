<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Dataset\Services\GeoDatasetService;
use ML\IDEA\Geo\GeoFeatureBuilder;
use ML\IDEA\Geo\GeoService;
use ML\IDEA\NLP\Lexicon\WordNetLexicon;
use ML\IDEA\NLP\Sentiment\SentimentAnalyzer;
use ML\IDEA\NLP\Translate\EnglishBembaTranslator;
use PHPUnit\Framework\TestCase;

final class DatasetGeoSentimentTest extends TestCase
{
    public function testWordNetLexiconProvidesData(): void
    {
        $path = $this->writeJson([
            'words' => ['dog' => ['dog.n.01']],
            'synsets' => [
                'dog.n.01' => [
                    'definition' => 'a domesticated canid',
                    'synonyms' => ['dog', 'domestic_dog'],
                ],
            ],
        ]);

        $lexicon = new WordNetLexicon($path);
        $synonyms = $lexicon->synonyms('dog', 10);

        self::assertNotEmpty($synonyms);
        self::assertNotNull($lexicon->definition('dog'));
    }

    public function testGeoServiceLookupsWork(): void
    {
        $base = $this->makeTempDir('geo_');
        mkdir($base . '/geo');
        file_put_contents($base . '/geo/countries+states.json', json_encode([
            ['name' => 'Zambia', 'iso2' => 'ZM', 'iso3' => 'ZMB', 'states' => [['name' => 'Lusaka Province']]],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($base . '/geo/countries.json', json_encode([
            ['name' => ['common' => 'Zambia'], 'latlng' => [-13.0, 28.0]],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($base . '/geo/cities.json', json_encode([
            ['name' => 'Lusaka', 'country_code' => 'ZM', 'state_code' => 'LUS', 'latitude' => '-15.4167', 'longitude' => '28.2833'],
        ], JSON_THROW_ON_ERROR));

        $geo = new GeoService(new GeoDatasetService($base . '/geo'));

        $country = $geo->country('ZM');
        self::assertNotNull($country);
        self::assertSame('Zambia', $country['name'] ?? null);

        $states = $geo->statesOf('Zambia');
        self::assertNotEmpty($states);

        $nearest = $geo->nearestCity(-15.4167, 28.2833, 'ZM');
        self::assertNotNull($nearest);
        self::assertSame('ZM', $nearest['country_code'] ?? null);

        $nearestMany = $geo->nearestCities(-15.4167, 28.2833, 3);
        self::assertNotEmpty($nearestMany);
        self::assertSame('Lusaka', $nearestMany[0]['name'] ?? null);

        $resolvedCountry = $geo->countryFromCoordinates(-15.4167, 28.2833);
        self::assertNotNull($resolvedCountry);
        self::assertSame('Zambia', $resolvedCountry['name'] ?? null);

        $placeHits = $geo->searchPlace('Luska', 3); // fuzzy typo for Lusaka
        self::assertNotEmpty($placeHits);
        self::assertSame('CITY', $placeHits[0]['type'] ?? null);
    }

    public function testTranslatorAndSentimentAnalyzerWork(): void
    {
        $translator = new EnglishBembaTranslator([
            'above' => ['pa mulu'],
            'abdomen' => ['ifumo'],
        ]);
        $translated = $translator->translate('Above Abdomen');
        self::assertNotSame('', $translated);

        $sentiment = new SentimentAnalyzer();
        $sentiment->train(
            ['amazing brilliant delightful', 'awful terrible bad'],
            ['positive', 'negative']
        );

        $label = $sentiment->predict('amazing brilliant delightful product');
        self::assertContains($label, ['positive', 'negative']);
        $proba = $sentiment->predictProba('terrible bad awful result');
        self::assertArrayHasKey('positive', $proba);
        self::assertArrayHasKey('negative', $proba);
    }

    public function testGeoFeatureBuilderBuildsFeatureVector(): void
    {
        $base = $this->makeTempDir('geo_feat_');
        mkdir($base . '/geo', 0777, true);
        file_put_contents($base . '/geo/countries+states.json', json_encode([
            ['name' => 'Zambia', 'iso2' => 'ZM', 'iso3' => 'ZMB', 'states' => []],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($base . '/geo/countries.json', json_encode([
            ['cca2' => 'ZM', 'name' => ['common' => 'Zambia'], 'latlng' => [-13.0, 28.0]],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($base . '/geo/cities.json', json_encode([
            ['name' => 'Lusaka', 'country_code' => 'ZM', 'state_code' => '', 'latitude' => '-15.4167', 'longitude' => '28.2833'],
        ], JSON_THROW_ON_ERROR));

        $geo = new GeoService(new GeoDatasetService($base . '/geo'));
        $builder = new GeoFeatureBuilder($geo);
        $vec = $builder->buildForCoordinate(-15.42, 28.28);

        self::assertCount(4, $vec);
        self::assertIsFloat($vec[2]);
    }

    private function writeJson(array $data): string
    {
        $file = tempnam(sys_get_temp_dir(), 'mlidea_json_');
        if ($file === false) {
            self::fail('Unable to create temp file.');
        }

        file_put_contents($file, json_encode($data, JSON_THROW_ON_ERROR));
        return $file;
    }

    private function makeTempDir(string $prefix): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . uniqid('', true);
        mkdir($dir, 0777, true);
        return $dir;
    }
}
