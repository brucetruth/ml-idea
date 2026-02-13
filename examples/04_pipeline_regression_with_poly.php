<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\Metrics\RegressionMetrics;
use ML\IDEA\Pipeline\PipelineRegressor;
use ML\IDEA\Preprocessing\PolynomialFeatures;
use ML\IDEA\Preprocessing\StandardScaler;
use ML\IDEA\Regression\LinearRegression;

// Non-linear trend: y ≈ x^2 + noise
$samples = [[-3.0], [-2.0], [-1.0], [0.0], [1.0], [2.0], [3.0], [4.0]];
$targets = [9.3, 4.2, 1.1, 0.2, 1.0, 4.1, 9.2, 16.3];

$pipeline = new PipelineRegressor(
    [new PolynomialFeatures(2), new StandardScaler()],
    new LinearRegression(learningRate: 0.05, iterations: 6000)
);

$pipeline->fit($samples, $targets);
$pred = $pipeline->predictBatch($samples);
$singlePred = $pipeline->predict($samples[0]);

echo "Example 04 - Pipeline Regression (Polynomial + Scaling)\n";
echo 'Single prediction for ' . json_encode($samples[0], JSON_THROW_ON_ERROR) . ': ' . round((float) $singlePred, 4) . PHP_EOL;
echo 'RMSE: ' . round(RegressionMetrics::rootMeanSquaredError($targets, $pred), 4) . PHP_EOL;
echo 'MAE: ' . round(RegressionMetrics::meanAbsoluteError($targets, $pred), 4) . PHP_EOL;
echo 'MAPE: ' . round(RegressionMetrics::meanAbsolutePercentageError($targets, $pred), 4) . PHP_EOL;
