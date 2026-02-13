<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\Classifiers\KNearestNeighbors;
use ML\IDEA\Data\TrainTestSplit;
use ML\IDEA\Metrics\ClassificationMetrics;
use ML\IDEA\Preprocessing\StandardScaler;

echo "Example 28: Hyperparameters + Random State Helpers\n\n";

$samples = [
    [1.0, 1.0], [1.0, 2.0], [2.0, 1.0],
    [4.0, 4.0], [5.0, 5.0], [4.0, 5.0],
];
$labels = ['A', 'A', 'A', 'B', 'B', 'B'];

$split = TrainTestSplit::split($samples, $labels, testSize: 0.33, seed: 42);

$scaler = new StandardScaler();
$xTrain = $scaler->fitTransform($split['xTrain']);
$xTest = $scaler->transform($split['xTest']);

$model = new KNearestNeighbors(k: 3, weighted: true);
$model = $model->setRandomState(42);

echo "1) Base params\n";
print_r($model->getParams());

$cloneA = $model->setParams(['k' => 1]);
$cloneB = $model->cloneWithParams(['k' => 5, 'weighted' => false]);

echo "\n2) Cloned params (setParams / cloneWithParams)\n";
echo "setParams => k=" . $cloneA->getParams()['k'] . "\n";
echo "cloneWithParams => k=" . $cloneB->getParams()['k'] . ", weighted=" . ($cloneB->getParams()['weighted'] ? 'true' : 'false') . "\n";

$model->fit($xTrain, $split['yTrain']); // contract alias of train()
$pred = $model->predictBatch($xTest);

$acc = ClassificationMetrics::accuracy($split['yTest'], $pred);
echo "\n3) Fit/Predict result\n";
echo "Accuracy: " . round($acc * 100, 2) . "%\n";
