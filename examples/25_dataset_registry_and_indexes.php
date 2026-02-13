<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\Dataset\Registry\DatasetIndex;
use ML\IDEA\Dataset\Registry\DatasetRegistry;
use ML\IDEA\Geo\GeoService;

echo "Example 25 - Dataset registry + compiled indexes\n";

$base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mlidea_ds_example_' . uniqid('', true);
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

$registry = new DatasetRegistry($base);
$datasets = $registry->listDatasets();
echo 'Datasets registered: ' . count($datasets) . PHP_EOL;

$integrity = $registry->integrityReport();
echo 'Geo cities exists: ' . (($integrity['geo-cities']['exists'] ?? false) ? 'yes' : 'no') . PHP_EOL;

$index = new DatasetIndex($base);
$trie = $index->geoTrie(5000);
echo 'Trie has "lusaka": ' . ($trie->contains('lusaka') ? 'yes' : 'no') . PHP_EOL;

$geo = new GeoService(index: $index);
$nearest = $geo->nearestCity(-15.4167, 28.2833);
echo 'Nearest city via indexed search: ' . (($nearest['name'] ?? 'n/a')) . PHP_EOL;
