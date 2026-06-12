<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\Classifiers\LogisticRegression;

$samples = [
    [0.0, 0.0], [0.2, -0.1], [-0.1, 0.2],
    [3.0, 3.0], [2.8, 3.1], [3.2, 2.9],
    [-3.0, 3.0], [-2.9, 2.8], [-3.2, 3.1],
];

$labels = [
    'red', 'red', 'red',
    'green', 'green', 'green',
    'blue', 'blue', 'blue',
];

$model = new LogisticRegression(learningRate: 0.1, iterations: 2500, l2Penalty: 0.001);
$model->train($samples, $labels);

$toPredict = [
    [0.1, 0.0],
    [3.1, 3.0],
    [-3.1, 3.0],
];

foreach ($toPredict as $sample) {
    $predicted = $model->predict($sample);
    $proba = $model->predictProba($sample);

    echo 'Sample: [' . implode(', ', array_map(static fn (float $v): string => (string) $v, $sample)) . ']' . PHP_EOL;
    echo 'Predicted class: ' . $predicted . PHP_EOL;
    echo 'Probabilities: ' . json_encode($proba, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    echo str_repeat('-', 40) . PHP_EOL;
}

