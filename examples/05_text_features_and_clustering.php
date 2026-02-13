<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\Clustering\MiniBatchKMeans;
use ML\IDEA\Decomposition\PCA;
use ML\IDEA\NLP\TfidfVectorizer;

$docs = [
    'php machine learning library',
    'ml toolkit in php for production',
    'football and sports analytics',
    'soccer game statistics and league data',
    'deep learning and ai systems',
    'neural models and ai research',
];

$tfidf = new TfidfVectorizer();
$x = $tfidf->fitTransform($docs);

// Dimensionality reduction before clustering (helpful in high-dimensional text spaces)
$pca = new PCA(nComponents: 2, powerIterations: 40);
$z = $pca->fitTransform($x);

$kmeans = new MiniBatchKMeans(k: 3, maxIterations: 120, batchSize: 3, seed: 42);
$kmeans->fit($z);
$clusters = $kmeans->predictBatch($z);
$singleCluster = $kmeans->predict($z[0]);

echo "Example 05 - Text Features + PCA + Clustering\n";
echo 'Single prediction for doc[0]: cluster=' . $singleCluster . PHP_EOL;
foreach ($docs as $i => $doc) {
    echo sprintf("[%d] cluster=%d | %s\n", $i, $clusters[$i], $doc);
}
