<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\Classifiers\LogisticRegression;
use ML\IDEA\Dataset\Registry\DatasetCache;
use ML\IDEA\Dataset\Registry\DatasetIndex;
use ML\IDEA\Geo\GeoFeatureBuilder;
use ML\IDEA\Geo\GeoService;

echo "Example 23 - ML-GEO foundation\n";

// Real bundled dataset by default.
ini_set('memory_limit', '768M');
$geoCacheDir = __DIR__ . '/artifacts/geo_index_cache';
if (!is_dir($geoCacheDir)) {
    mkdir($geoCacheDir, 0777, true);
}
$index = new DatasetIndex(cache: new DatasetCache($geoCacheDir));
$geo = new GeoService(index: $index);

// Optional override only (not required): you may pass a custom GeoDatasetService.
$builder = new GeoFeatureBuilder($geo);

$coordinates = [
    [-15.4167, 28.2833], // Lusaka area
    [-1.286389, 36.817223], // Nairobi area
    [51.5074, -0.1278], // London area
    [0.0, -140.0], // remote ocean point
    [10.0, -30.0], // remote ocean point
    [-40.0, 80.0], // remote ocean point
];

$labels = ['urban', 'urban', 'urban', 'remote', 'remote', 'remote'];

$x = $builder->buildBatch($coordinates);

$model = new LogisticRegression(learningRate: 0.1, iterations: 400, l2Penalty: 0.001);
$model->train($x, $labels);

$test = [
    [-15.5, 28.2],
    [5.0, -120.0],
];

foreach ($test as $point) {
    $features = $builder->buildForCoordinate($point[0], $point[1]);
    $pred = $model->predict($features);
    echo 'Point (' . $point[0] . ', ' . $point[1] . ') => ' . $pred . PHP_EOL;
}
