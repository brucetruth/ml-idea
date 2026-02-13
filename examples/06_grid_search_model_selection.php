<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\Classifiers\KNearestNeighbors;
use ML\IDEA\ModelSelection\GridSearchClassifier;

$samples = [
    [1, 1], [1, 2], [2, 1], [2, 2],
    [7, 7], [8, 7], [7, 8], [8, 8],
    [3, 3], [6, 6],
];
$labels = ['A', 'A', 'A', 'A', 'B', 'B', 'B', 'B', 'A', 'B'];

$search = new GridSearchClassifier(
    static fn (array $p): KNearestNeighbors => new KNearestNeighbors(
        k: (int) $p['k'],
        weighted: (bool) $p['weighted']
    ),
    scoring: 'accuracy',
    cv: 4
);

$search->fit($samples, $labels, [
    'k' => [1, 3, 5],
    'weighted' => [true, false],
]);

$best = $search->bestEstimator();
$probe = [7.4, 7.3];

echo "Example 06 - Grid Search Model Selection\n";
echo 'Best params: ' . json_encode($search->bestParams(), JSON_THROW_ON_ERROR) . PHP_EOL;
echo 'Best CV score: ' . round($search->bestScore(), 4) . PHP_EOL;
echo 'Prediction for [7.4, 7.3]: ' . json_encode($best->predict($probe), JSON_THROW_ON_ERROR) . PHP_EOL;
