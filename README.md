# ml-idea

[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%208.2-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/github/license/brucetruth/ml-idea.svg)](LICENSE)

`ml-idea` is a modern, production-oriented machine learning library for PHP focused on clean APIs,
strict typing, and practical classification workflows.

## Features

- PHP 8.2+ with strict types
- Consistent classifier contract (`train`, `predict`, `predictBatch`)
- Production-ready baseline classifiers:
  - `KNearestNeighbors`
  - `LogisticRegression` (binary classification)
  - `GaussianNaiveBayes`
- Model persistence (`ModelSerializer`)
- Data splitting utility (`TrainTestSplit`)
- Evaluation metrics (`accuracy`, `precision`, `recall`, `f1Score`)
- Advanced evaluation metrics: `rocAuc`, `prAuc`, `logLoss`, `brierScore`, `matthewsCorrcoef`, `meanAbsolutePercentageError`
- Preprocessing transformers (`StandardScaler`, `MinMaxScaler`)
- Workflow tools (`PipelineClassifier`, `KFold` cross-validation splits)
- Extra splitters: `StratifiedKFold`, `TimeSeriesSplit`
- Cross-validation helpers: `CrossValidation::crossValScore*`, `CrossValidation::crossValPredict*`
- Probability calibration + threshold optimization: `CalibratedClassifierCV`, `ThresholdTuner`
- Regression support (`LinearRegression`, `RegressionMetrics`)
- Advanced modules: `PCA`, `MiniBatchKMeans`, `TfidfVectorizer`
- Vision module foundations: generic image feature extraction + color palette analysis + skin-tone risk heuristics
- Vision authenticity heuristic: AI-generation risk scoring from metadata and statistical image signals
- NLP foundation (Phase 1): fluent Text API, unicode tokenization with offsets, PII redaction, rule-based POS tagging
- NLP Phase 2: language detection, keyword extraction (RAKE), BM25 retrieval, hashing vectorizer, similarity utilities, and NLP RAG helpers
- NLP advanced tagging: multilingual rule-based POS, extensible language profiles, and rule-based NER
- GEO service + ML-GEO helpers: country/state/city lookup, nearest-place search, and geo feature building
- Managed dataset assets: registry, integrity checks, licenses metadata, and compiled indexes (trie/automaton/kd-tree)
- RAG foundations: embedders (`OpenAI`, `AzureOpenAI`, `Ollama`), splitters, retriever, and multiple vector stores (in-memory, JSON, SQLite)
- RAG LLM clients for QA generation: `Echo`, `OpenAI`, `Azure OpenAI`, and `Ollama` (direct or `LlmClientFactory::fromEnv()`)
- Advanced RAG workflow: document loaders, hybrid retrieval, rerankers, citations/diagnostics, vector-index persistence, tool-calling + streaming hooks
- AI agents + tool routing: `ToolCallingAgent`, `ToolRoutingAgent`, deterministic/local routing, and provider-backed routing (OpenAI/Azure/Ollama/custom)
- Unified core contracts (v1.4): `fit/predict`, probabilistic, online-learning, serializable model interfaces
- Hyperparameter lifecycle helpers: `getParams`, `setParams`, `cloneWithParams`, random-state aware models
- PHPUnit test suite + CI workflow
- Static analysis support with PHPStan

## Installation

```bash
composer require brucetruth/ml-idea
```

## Quick Start

```php
<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';

use ML\IDEA\Classifiers\KNearestNeighbors;
use ML\IDEA\Data\TrainTestSplit;
use ML\IDEA\Metrics\ClassificationMetrics;
use ML\IDEA\Preprocessing\StandardScaler;

$samples = [[1, 1], [1, 2], [2, 1], [4, 4], [5, 5], [4, 5]];
$labels = ['A', 'A', 'A', 'B', 'B', 'B'];

$split = TrainTestSplit::split($samples, $labels, testSize: 0.33, seed: 42);

$scaler = new StandardScaler();
$xTrain = $scaler->fitTransform($split['xTrain']);
$xTest = $scaler->transform($split['xTest']);

$model = new KNearestNeighbors(k: 3, weighted: true);
$model->train($xTrain, $split['yTrain']);

$predictions = $model->predictBatch($xTest);
$accuracy = ClassificationMetrics::accuracy($split['yTest'], $predictions);

echo "Accuracy: " . round($accuracy * 100, 2) . "%\n";
```

## Model Persistence

```php
use ML\IDEA\Model\ModelSerializer;

ModelSerializer::save($model, __DIR__ . '/knn.model.json');
$loadedModel = ModelSerializer::load(__DIR__ . '/knn.model.json');
```

## Advanced v1.2 Examples

### 1) Pipeline + KFold

```php
use ML\IDEA\Classifiers\KNearestNeighbors;
use ML\IDEA\Data\KFold;
use ML\IDEA\Pipeline\PipelineClassifier;
use ML\IDEA\Preprocessing\StandardScaler;

$samples = [[1,1],[1,2],[2,1],[4,4],[5,5],[4,5]];
$labels = ['A','A','A','B','B','B'];

$folds = KFold::split(count($samples), nSplits: 3, shuffle: true, seed: 42);
foreach ($folds as $fold) {
    $xTrain = $yTrain = $xTest = $yTest = [];
    foreach ($fold['train'] as $i) { $xTrain[] = $samples[$i]; $yTrain[] = $labels[$i]; }
    foreach ($fold['test'] as $i) { $xTest[] = $samples[$i]; $yTest[] = $labels[$i]; }

    $model = new PipelineClassifier([new StandardScaler()], new KNearestNeighbors(3, true));
    $model->train($xTrain, $yTrain);
    $pred = $model->predictBatch($xTest);
}
```

### 2) Linear Regression

```php
use ML\IDEA\Regression\LinearRegression;
use ML\IDEA\Metrics\RegressionMetrics;

$x = [[1.0], [2.0], [3.0], [4.0]];
$y = [2.0, 4.0, 6.0, 8.0];

$reg = new LinearRegression(learningRate: 0.05, iterations: 5000);
$reg->train($x, $y);
$pred = $reg->predictBatch($x);

echo RegressionMetrics::rootMeanSquaredError($y, $pred);
```

### 3) Text Embedding (TF-IDF)

```php
use ML\IDEA\NLP\TfidfVectorizer;

$docs = ['machine learning in php', 'php library for intelligence'];
$vectorizer = new TfidfVectorizer();
$matrix = $vectorizer->fitTransform($docs);
```

## Development

```bash
composer install
composer test
composer analyse
```

## Examples

See runnable use-case scripts in [`examples/`](examples/README.md):

- basic classification flow
- CV + advanced metrics
- probability calibration + threshold tuning
- regression pipelines
- text features + clustering
- hyperparameter search
- RAG local chain + vector-store examples
- RAG DB loader example (SQLite/PDO)
- Agent toolbox example (`examples/agents`) with local KB + weather + free API tools
- Vision examples (palette extraction and content-risk heuristic demo)
- Vision authenticity-risk example (AI-generated likelihood heuristic)
- NLP Text API + POS example (`examples/16_nlp_text_api_and_pos.php`)
- NLP BM25 + similarity example (`examples/17_nlp_bm25_and_similarity.php`)
- NLP multilingual POS + NER example (`examples/18_nlp_multilingual_ner.php`)
- NLP extensibility example (`examples/19_nlp_extensibility_custom_profiles.php`)
- NLP trainable POS/NER pipeline example (`examples/20_nlp_trainable_pos_ner.php`)

## Roadmap

- More algorithms (tree-based models, multiclass linear models)
- Feature preprocessing (normalization, encoding)
- Cross-validation utilities
- Dataset loaders and richer benchmarking tools
- Context and chat history handling for the Tool Routing Agent
- Tool reliability layer for agents (timeouts, retries, fallbacks, structured errors)
- Policy and safety guardrails (tool allow/deny rules, injection checks, PII-safe logs)
- Improved routing quality (confidence scoring, clarification turn, top-k tool candidates)
- Observability + evaluation harness for routing/tool accuracy regressions
- Memory strategy beyond raw history (summaries, pruning, retrieval-based recall)
- Cost/latency controls (model tiering, caching, token budgets)
- Human-in-the-loop controls for risky actions and execution approvals
- Output quality controls (schema validation, grounding/citation checks, consistency pass)