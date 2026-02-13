<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\Classifiers\KNearestNeighbors;

/**
 * Example pattern: persist a full inference bundle.
 *
 * Current library serializes models implementing PersistableModelInterface.
 * For pipeline persistence, you can bundle:
 * - trained model artifact
 * - preprocessing statistics (or config)
 * - feature schema / version metadata
 */

$samples = [
    [1, 1], [1, 2], [2, 1], [2, 2],
    [8, 8], [8, 9], [9, 8], [9, 9],
];
$labels = ['A', 'A', 'A', 'A', 'B', 'B', 'B', 'B'];

// Build simple scaler stats outside model for explicit persistence.
$featureCount = count($samples[0]);
$means = array_fill(0, $featureCount, 0.0);
$std = array_fill(0, $featureCount, 1.0);

foreach ($samples as $sample) {
    foreach ($sample as $j => $value) {
        $means[$j] += (float) $value;
    }
}
foreach ($means as $j => $sum) {
    $means[$j] = $sum / count($samples);
}
foreach ($samples as $sample) {
    foreach ($sample as $j => $value) {
        $d = (float) $value - $means[$j];
        $std[$j] += $d * $d;
    }
}
foreach ($std as $j => $sumSquares) {
    $variance = $sumSquares / max(1, count($samples) - 1);
    $std[$j] = max(1.0e-12, sqrt($variance));
}

$transform = static function (array $x) use ($means, $std): array {
    $out = [];
    foreach ($x as $row) {
        $scaled = [];
        foreach ($row as $j => $v) {
            $scaled[] = ((float) $v - $means[$j]) / $std[$j];
        }
        $out[] = $scaled;
    }

    return $out;
};

$xTrain = $transform($samples);

$model = new KNearestNeighbors(k: 3, weighted: true);
$model->fit($xTrain, $labels);

$bundleDir = __DIR__ . '/artifacts/pipeline_bundle_v1';
if (!is_dir($bundleDir)) {
    mkdir($bundleDir, 0777, true);
}

$modelPath = $bundleDir . '/classifier.model.json';
$metaPath = $bundleDir . '/bundle.meta.json';

$model->save($modelPath);

$bundleMeta = [
    'bundle_name' => 'knn-standardize-v1',
    'created_at_utc' => gmdate(DATE_ATOM),
    'feature_schema' => ['x1', 'x2'],
    'preprocessing' => [
        'type' => 'manual_standardize',
        'means' => $means,
        'std' => $std,
    ],
    'model' => [
        'path' => basename($modelPath),
        'class' => KNearestNeighbors::class,
        'k' => 3,
        'weighted' => true,
    ],
    'serving_contract' => [
        'expected_order' => ['x1', 'x2'],
        'output' => 'label',
    ],
];

file_put_contents($metaPath, json_encode($bundleMeta, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

// Reload bundle and run inference.
$loadedModel = KNearestNeighbors::load($modelPath);
$loadedMeta = json_decode((string) file_get_contents($metaPath), true, 512, JSON_THROW_ON_ERROR);

$probe = [[8.5, 8.4]];
$scaledProbe = [];
foreach ($probe as $row) {
    $scaledRow = [];
    foreach ($row as $j => $value) {
        $scaledRow[] = ((float) $value - (float) $loadedMeta['preprocessing']['means'][$j]) / (float) $loadedMeta['preprocessing']['std'][$j];
    }
    $scaledProbe[] = $scaledRow;
}

$prediction = $loadedModel->predict($scaledProbe[0]);

echo "Example 08 - Pipeline Persistence Bundle + Metadata\n";
echo "Bundle dir: {$bundleDir}\n";
echo "Prediction from restored bundle: " . json_encode($prediction, JSON_THROW_ON_ERROR) . PHP_EOL;
