<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\Classifiers\LogisticRegression;
use ML\IDEA\Metrics\ClassificationMetrics;

$samples = [[0, 0], [0, 1], [1, 0], [1, 1], [0.9, 0.8], [0.1, 0.2]];
$labels = [0, 1, 1, 1, 1, 0];

$model = new LogisticRegression(learningRate: 0.2, iterations: 2000);
$model->fit($samples, $labels);

$artifactDir = __DIR__ . '/artifacts';
if (!is_dir($artifactDir)) {
    mkdir($artifactDir, 0777, true);
}

$modelPath = $artifactDir . '/logistic.model.json';
$metaPath = $artifactDir . '/logistic.meta.json';

$model->save($modelPath);

$pred = $model->predictBatch($samples);
$meta = [
    'artifact' => 'logistic-regression-v1',
    'model_class' => LogisticRegression::class,
    'created_at_utc' => gmdate(DATE_ATOM),
    'library' => 'ml-idea',
    'php_version' => PHP_VERSION,
    'feature_count' => count($samples[0]),
    'training_metrics' => [
        'accuracy' => ClassificationMetrics::accuracy($labels, $pred),
        'f1' => ClassificationMetrics::f1Score($labels, $pred, 1),
    ],
    'inference_notes' => [
        'positive_class' => 1,
        'threshold' => 0.5,
    ],
];

file_put_contents($metaPath, json_encode($meta, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

$loaded = LogisticRegression::load($modelPath);
$probe = [0.95, 0.95];
$proba = $loaded->predictProba($probe);

echo "Example 07 - Save/Load Classifier + Metadata\n";
echo "Saved model: {$modelPath}\n";
echo "Saved metadata: {$metaPath}\n";
echo 'Prediction after reload: ' . json_encode($loaded->predict($probe), JSON_THROW_ON_ERROR) . PHP_EOL;
echo 'Probability after reload: ' . json_encode($proba, JSON_THROW_ON_ERROR) . PHP_EOL;
