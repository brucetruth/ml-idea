<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\Calibration\CalibratedClassifierCV;
use ML\IDEA\Calibration\ThresholdTuner;
use ML\IDEA\Classifiers\LogisticRegression;
use ML\IDEA\Metrics\ClassificationMetrics;

$samples = [[0, 0], [0, 1], [1, 0], [1, 1], [0.8, 0.9], [0.1, 0.2], [0.95, 0.7], [0.2, 0.05]];
$labels = [0, 1, 1, 1, 1, 0, 1, 0];

$base = new LogisticRegression(learningRate: 0.2, iterations: 2000);
$calibrated = new CalibratedClassifierCV($base, cv: 4, iterations: 250, learningRate: 0.1);
$calibrated->fit($samples, $labels);

$scores = [];
foreach ($samples as $sample) {
    $proba = $calibrated->predictProba($sample);
    $scores[] = (float) ($proba[1] ?? array_values($proba)[0]);
}

$threshold = ThresholdTuner::optimize($labels, $scores, 1, metric: 'f1', steps: 51);
$pred = ThresholdTuner::apply($scores, $threshold, 1, 0);

echo "Example 03 - Calibration + Threshold Tuning\n";
echo 'Chosen threshold (F1): ' . round($threshold, 4) . PHP_EOL;
echo 'F1 after tuning: ' . round(ClassificationMetrics::f1Score($labels, $pred, 1), 4) . PHP_EOL;
