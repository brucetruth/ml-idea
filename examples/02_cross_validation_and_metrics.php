<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\Classifiers\LogisticRegression;
use ML\IDEA\Data\KFold;
use ML\IDEA\Metrics\ClassificationMetrics;
use ML\IDEA\ModelSelection\CrossValidation;

$samples = [[0, 0], [0, 1], [1, 0], [1, 1], [0.8, 0.9], [0.1, 0.2], [0.9, 0.7], [0.2, 0.1]];
$labels = [0, 1, 1, 1, 1, 0, 1, 0];

$model = new LogisticRegression(learningRate: 0.2, iterations: 2000);
$folds = KFold::split(count($samples), 4, true, 42);

$cvAccuracy = CrossValidation::crossValScoreClassifier($model, $samples, $labels, $folds, 'accuracy');
$cvRocAuc = CrossValidation::crossValScoreClassifier($model, $samples, $labels, $folds, 'roc_auc');

$model->fit($samples, $labels);
$scores = [];
foreach ($samples as $sample) {
    $proba = $model->predictProba($sample);
    $scores[] = (float) ($proba[1] ?? array_values($proba)[0]);
}

echo "Example 02 - Cross Validation + Advanced Metrics\n";
echo 'CV Accuracy Mean: ' . round(array_sum($cvAccuracy) / count($cvAccuracy), 4) . PHP_EOL;
echo 'CV ROC AUC Mean: ' . round(array_sum($cvRocAuc) / count($cvRocAuc), 4) . PHP_EOL;
echo 'LogLoss: ' . round(ClassificationMetrics::logLoss($labels, $scores, 1), 4) . PHP_EOL;
echo 'PR AUC: ' . round(ClassificationMetrics::prAuc($labels, $scores, 1), 4) . PHP_EOL;
