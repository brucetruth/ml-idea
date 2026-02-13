<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\Dataset\Registry\DatasetCache;
use ML\IDEA\Dataset\Services\GeoChunkedIndexBuilder;

echo "Example 27 - Chunked geo index build + file cache\n";

$cacheDir = __DIR__ . '/artifacts/geo_index_cache';
$builder = new GeoChunkedIndexBuilder(cache: new DatasetCache($cacheDir));

$cityIdx = $builder->cityNameIndex(15000);
$phoneIdx = $builder->countryPhoneIndex();

echo 'Indexed city keys: ' . count($cityIdx) . PHP_EOL;
echo 'Indexed phone codes: ' . count($phoneIdx) . PHP_EOL;
echo 'Cache dir: ' . $cacheDir . PHP_EOL;
