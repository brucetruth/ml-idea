<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\Classifiers\KNearestNeighbors;
use ML\IDEA\Data\TrainTestSplit;
use ML\IDEA\Metrics\ClassificationMetrics;
use ML\IDEA\Preprocessing\StandardScaler;

$samples = [
    [1, 1], [1, 2], [2, 1], [2, 2],
    [8, 8], [8, 9], [9, 8], [9, 9],
];
$labels = ['A', 'A', 'A', 'A', 'B', 'B', 'B', 'B'];

$split = TrainTestSplit::split($samples, $labels, testSize: 0.25, seed: 42);

$scaler = new StandardScaler();
$xTrain = $scaler->fitTransform($split['xTrain']);
$xTest = $scaler->transform($split['xTest']);

$model = new KNearestNeighbors(k: 3, weighted: true);
$model->fit($xTrain, $split['yTrain']);
$pred = $model->predictBatch($xTest);
$singlePred = $model->predict($xTest[0]);

echo "Example 01 - Basic Classification\n";
echo 'Predictions: ' . json_encode($pred, JSON_THROW_ON_ERROR) . PHP_EOL;
echo 'Single prediction (first test row): ' . json_encode($singlePred, JSON_THROW_ON_ERROR) . PHP_EOL;
echo 'Accuracy: ' . round(ClassificationMetrics::accuracy($split['yTest'], $pred), 4) . PHP_EOL;
