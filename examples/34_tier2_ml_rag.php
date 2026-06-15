<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\Calibration\CalibratedClassifierCV;
use ML\IDEA\Classifiers\LogisticRegression;
use ML\IDEA\Clustering\DBSCAN;
use ML\IDEA\Clustering\KMeans;
use ML\IDEA\Data\SparseVector;
use ML\IDEA\Metrics\ClusteringMetrics;
use ML\IDEA\NLP\Backends\CallableNlpBackend;
use ML\IDEA\NLP\Backends\HuggingFaceInferenceBackend;
use ML\IDEA\NLP\Backends\OllamaNlpBackend;
use ML\IDEA\NLP\Nlp;
use ML\IDEA\NLP\TfidfVectorizer;
use ML\IDEA\RAG\VectorStore\AnnVectorStore;

echo "Example 34 - Tier-2 ML/RAG (sparse TF-IDF, clustering, ANN, multiclass calibration)\n\n";

// Sparse TF-IDF → densify for downstream ML
$docs = [
    'machine learning in php',
    'php library for machine intelligence',
    'learning systems and intelligence',
    'clustering with kmeans and dbscan',
];
$tfidf = new TfidfVectorizer(outputSparse: true);
$sparse = $tfidf->fitTransform($docs);
$dense = $tfidf->densify($sparse);
echo 'Sparse TF-IDF nonzeros (doc 0): ' . count($sparse[0]) . ' / vocab ' . count($tfidf->getVocabulary()) . PHP_EOL;

$kmeans = new KMeans(k: 2, seed: 42);
$kmeans->fit($dense);
$labels = $kmeans->predictBatch($dense);
echo 'KMeans labels: ' . json_encode($labels, JSON_THROW_ON_ERROR) . PHP_EOL;
echo 'Silhouette: ' . round(ClusteringMetrics::silhouetteScore($dense, $labels), 4) . PHP_EOL;

$db = new DBSCAN(eps: 0.8, minSamples: 2);
$dbLabels = $db->fitPredict($dense);
echo 'DBSCAN labels (-1=noise): ' . json_encode($dbLabels, JSON_THROW_ON_ERROR) . PHP_EOL;

// Multiclass calibration
$samples = [[0, 0], [0, 1], [1, 0], [3, 3], [3, 4], [4, 3], [6, 6], [6, 7], [7, 6]];
$multiclass = ['A', 'A', 'A', 'B', 'B', 'B', 'C', 'C', 'C'];
$cal = new CalibratedClassifierCV(new LogisticRegression(iterations: 600), cv: 3);
$cal->train($samples, $multiclass);
echo 'Multiclass calibrated proba [7,6]: ' . json_encode($cal->predictProba([7, 6]), JSON_THROW_ON_ERROR) . PHP_EOL;

// ANN vector store
$store = new AnnVectorStore(nlist: 4, nprobe: 2, minItemsForAnn: 8, seed: 42);
$items = [];
for ($i = 0; $i < 24; $i++) {
    $items[] = [
        'id' => 'chunk-' . $i,
        'vector' => [sin($i), cos($i), $i * 0.05],
        'text' => 'chunk about topic ' . ($i % 4),
        'metadata' => ['topic' => $i % 4],
    ];
}
$store->upsert($items);
$hits = $store->search([sin(20), cos(20), 1.0], k: 3);
echo 'ANN top hit: ' . ($hits[0]['id'] ?? 'none') . ' score=' . round((float) ($hits[0]['score'] ?? 0), 4) . PHP_EOL;

// NLP backends: CallableNlpBackend (always works) + OllamaNlpBackend (when Ollama is running)
$nlp = Nlp::blank('en')->withBackend(new CallableNlpBackend(
    static fn (string $text, $doc) => $doc,
));
echo 'CallableNlpBackend wired: ' . json_encode($nlp->process('Hello world.')->summary(), JSON_THROW_ON_ERROR) . PHP_EOL;
echo 'OllamaNlpBackend: ' . OllamaNlpBackend::class . ' (local Ollama on :11434)' . PHP_EOL;
echo 'HuggingFaceInferenceBackend: ' . HuggingFaceInferenceBackend::class . ' (set HF_API_TOKEN + model id)' . PHP_EOL;
