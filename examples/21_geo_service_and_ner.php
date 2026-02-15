<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\Dataset\Registry\DatasetCache;
use ML\IDEA\Dataset\Registry\DatasetIndex;
use ML\IDEA\Geo\GeoService;
use ML\IDEA\NLP\Ner\RuleBasedNerTagger;

echo "Example 21 - GEO Service + GEO-aware NER\n";

// Real bundled dataset (default): src/Dataset/geo
// If your environment has low memory, increase memory limit first.
ini_set('memory_limit', '768M');
$geoCacheDir = __DIR__ . '/artifacts/geo_index_cache';
if (!is_dir($geoCacheDir)) {
    mkdir($geoCacheDir, 0777, true);
}
$index = new DatasetIndex(cache: new DatasetCache($geoCacheDir));
$geo = new GeoService(index: $index);

// Optional override only (not required): you may pass a custom GeoDatasetService.

$country = $geo->country('ZM');
echo 'Country (ZM): ' . (($country['name'] ?? 'n/a')) . PHP_EOL;

$states = $geo->statesOf('Zambia');
echo 'States in Zambia (sample): ' . implode(', ', array_slice($states, 0, 5)) . PHP_EOL;

$nearest = $geo->nearestCity(-15.4167, 28.2833, 'ZM');
echo 'Nearest city to Lusaka coords: ' . (($nearest['name'] ?? 'n/a')) . PHP_EOL;

$nearest3 = $geo->nearestCities(-15.4167, 28.2833, 3);
echo 'Nearest 3 cities count: ' . count($nearest3) . PHP_EOL;

$countryAtCoord = $geo->countryFromCoordinates(-15.4167, 28.2833);
echo 'Country from coordinates: ' . (($countryAtCoord['name'] ?? 'n/a'))
    . ' via ' . (($countryAtCoord['match_method'] ?? 'n/a')) . PHP_EOL;

$search = $geo->searchPlace('Ndola', 3);
echo 'searchPlace("Ndola") top type: ' . (($search[0]['type'] ?? 'n/a'))
    . ' name=' . (($search[0]['name'] ?? 'n/a')) . PHP_EOL;

$tagger = (new RuleBasedNerTagger())->withGeoGazetteer($geo, 8000);
$text = 'I travelled from Lusaka to Johannesburg and then to Nairobi.';
$entities = $tagger->extract($text);

echo "Entities:\n";
foreach ($entities as $entity) {
    if (in_array($entity->label, ['CITY', 'STATE', 'COUNTRY', 'PROPER_NOUN'], true)) {
        echo '- ' . $entity->text . ' [' . $entity->label . ']' . PHP_EOL;
    }
}
