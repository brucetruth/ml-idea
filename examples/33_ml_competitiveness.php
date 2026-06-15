<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\Calibration\CalibratedClassifierCV;
use ML\IDEA\Classifiers\LinearSVC;
use ML\IDEA\Classifiers\LogisticRegression;
use ML\IDEA\Ensemble\GradientBoostingClassifier;
use ML\IDEA\Ensemble\GradientBoostingRegressor;
use ML\IDEA\Ensemble\RandomForestClassifier;
use ML\IDEA\Explain\PermutationImportance;
use ML\IDEA\Metrics\ClusteringMetrics;
use ML\IDEA\Model\PipelineSerializer;
use ML\IDEA\ModelSelection\GridSearchClassifier;
use ML\IDEA\ModelSelection\RandomizedSearchClassifier;
use ML\IDEA\Pipeline\TabularPipelineClassifier;
use ML\IDEA\Preprocessing\OneHotEncoder;
use ML\IDEA\Preprocessing\StandardScaler;

echo "Example 33 - ML Competitiveness (trees, pipelines, search, calibration)\n\n";

// --- Real decision-tree ensembles ---
$samples = [[0, 0], [0, 1], [1, 0], [3, 3], [3, 4], [4, 3], [0.5, 0.5], [3.5, 3.5]];
$labels = [0, 0, 0, 1, 1, 1, 0, 1];

$rf = new RandomForestClassifier(nEstimators: 30, maxDepth: 3, seed: 42);
$rf->train($samples, $labels);
echo 'RandomForest predict: ' . $rf->predict([3.6, 3.4]) . PHP_EOL;
echo 'RandomForest proba: ' . json_encode($rf->predictProba([3.6, 3.4]), JSON_THROW_ON_ERROR) . PHP_EOL;

$gb = new GradientBoostingClassifier(nEstimators: 40, learningRate: 0.2, maxDepth: 2, seed: 42);
$gb->train($samples, $labels);
echo 'GradientBoosting predict: ' . $gb->predict([3.6, 3.4]) . PHP_EOL;

$regSamples = [[1.0], [2.0], [3.0], [4.0], [5.0]];
$regTargets = [2.0, 4.0, 6.0, 8.0, 10.0];
$gbr = new GradientBoostingRegressor(nEstimators: 40, learningRate: 0.2, maxDepth: 2, seed: 42);
$gbr->train($regSamples, $regTargets);
echo 'GradientBoostingRegressor predict [4.5]: ' . round($gbr->predict([4.5]), 2) . PHP_EOL;

$svc = new LinearSVC(iterations: 400);
$svc->train($samples, $labels);
echo 'LinearSVC predict: ' . $svc->predict([3.6, 3.4]) . PHP_EOL;

// --- Tabular pipeline with one-hot + persistence ---
$tabSamples = [['red', 1], ['red', 2], ['blue', 8], ['blue', 9], ['green', 7], ['green', 8]];
$tabLabels = [0, 0, 1, 1, 1, 1];

$tabular = new TabularPipelineClassifier(
    new OneHotEncoder(),
    [new StandardScaler()],
    new LogisticRegression(iterations: 500, batchSize: 2),
);
$tabular->train($tabSamples, $tabLabels);

$pipelinePath = sys_get_temp_dir() . '/ml-idea-example33-pipeline.json';
PipelineSerializer::save($tabular, $pipelinePath);
$restored = PipelineSerializer::loadClassifier($pipelinePath);
echo 'Tabular pipeline predict (restored): ' . $restored->predict(['blue', 9]) . PHP_EOL;
@unlink($pipelinePath);

// --- Hyperparameter search (stratified CV auto-capped) ---
$grid = new GridSearchClassifier(
    static fn (array $p): LogisticRegression => new LogisticRegression(iterations: (int) $p['iterations']),
    stratified: true,
);
$grid->fit($samples, $labels, ['iterations' => [200, 400]]);
echo 'GridSearch best params: ' . json_encode($grid->bestParams(), JSON_THROW_ON_ERROR) . PHP_EOL;

$random = new RandomizedSearchClassifier(
    static fn (array $p): LogisticRegression => new LogisticRegression(iterations: (int) $p['iterations']),
    seed: 42,
);
$random->fit($samples, $labels, ['iterations' => [200, 400, 600]], 2);
echo 'RandomizedSearch best params: ' . json_encode($random->bestParams(), JSON_THROW_ON_ERROR) . PHP_EOL;

// --- Isotonic calibration ---
$base = new LogisticRegression(iterations: 500);
$calibrated = new CalibratedClassifierCV($base, cv: 3, method: 'isotonic');
$calibrated->train($samples, $labels);
echo 'Calibrated proba: ' . json_encode($calibrated->predictProba([3.6, 3.4]), JSON_THROW_ON_ERROR) . PHP_EOL;

// --- Explainability + clustering quality ---
$importances = PermutationImportance::forClassifier($rf, $samples, $labels, nRepeats: 5, seed: 42);
echo 'Permutation importances (feature 0, 1): ' . json_encode($importances, JSON_THROW_ON_ERROR) . PHP_EOL;

$clusterSamples = [[1, 1], [1.2, 0.8], [5, 5], [5.2, 4.8], [9, 1], [9.1, 0.9]];
$clusterLabels = [0, 0, 1, 1, 2, 2];
echo 'Silhouette score: ' . round(ClusteringMetrics::silhouetteScore($clusterSamples, $clusterLabels), 4) . PHP_EOL;
