<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Dataset\Registry\DatasetCache;
use ML\IDEA\Dataset\Registry\DatasetIndex;
use ML\IDEA\Dataset\Registry\DatasetRegistry;
use ML\IDEA\Geo\GeoService;
use PHPUnit\Framework\TestCase;

final class DatasetRegistryIndexTest extends TestCase
{
    public function testRegistryIntegrityAndLicenses(): void
    {
        $base = $this->makeDatasetFixture();
        $registry = new DatasetRegistry($base);

        $datasets = $registry->listDatasets();
        self::assertNotEmpty($datasets);

        $integrity = $registry->integrityReport();
        self::assertTrue($integrity['geo-cities']['exists'] ?? false);

        $licenses = $registry->licenses();
        self::assertArrayHasKey('geo', $licenses);
    }

    public function testCompiledIndexesAndGeoServiceIndexedPath(): void
    {
        $base = $this->makeDatasetFixture();
        $cache = new DatasetCache(sys_get_temp_dir() . '/mlidea_cache_' . uniqid('', true));
        $index = new DatasetIndex($base, $cache);

        $map = $index->geoGazetteerMap(10);
        self::assertArrayHasKey('lusaka', $map);

        $trie = $index->geoTrie(10);
        self::assertTrue($trie->contains('lusaka'));

        $ac = $index->geoGazetteerAutomaton(10);
        $matches = $ac->find('travel to lusaka soon');
        self::assertNotEmpty($matches);

        $geo = new GeoService(index: $index);
        $nearest = $geo->nearestCity(-15.4167, 28.2833);
        self::assertNotNull($nearest);
        self::assertSame('Lusaka', $nearest['name'] ?? null);
    }

    private function makeDatasetFixture(): string
    {
        $base = sys_get_temp_dir() . '/mlidea_ds_' . uniqid('', true);
        mkdir($base . '/geo', 0777, true);
        mkdir($base . '/wordnet', 0777, true);
        mkdir($base . '/sentiment', 0777, true);
        mkdir($base . '/dictionary/en', 0777, true);
        mkdir($base . '/dictionary/bemba', 0777, true);

        file_put_contents($base . '/geo/countries+states.json', json_encode([
            ['name' => 'Zambia', 'iso2' => 'ZM', 'iso3' => 'ZMB', 'states' => [['name' => 'Lusaka Province']]],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($base . '/geo/countries.json', json_encode([
            ['cca2' => 'ZM', 'name' => ['common' => 'Zambia'], 'latlng' => [-13.0, 28.0]],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($base . '/geo/cities.json', json_encode([
            ['name' => 'Lusaka', 'country_code' => 'ZM', 'state_code' => '', 'latitude' => '-15.4167', 'longitude' => '28.2833'],
        ], JSON_THROW_ON_ERROR));

        file_put_contents($base . '/wordnet/wn.json', json_encode(['words' => [], 'synsets' => []], JSON_THROW_ON_ERROR));
        file_put_contents($base . '/sentiment/sentiment_dataset.json', json_encode([], JSON_THROW_ON_ERROR));
        file_put_contents($base . '/dictionary/en/en.csv', "word,definition\na,b\n");
        file_put_contents($base . '/dictionary/bemba/english_to_bemba.csv', "id,English,Bemba,Bemba2\n1,hello,muli shani,\n");

        return $base;
    }
}
