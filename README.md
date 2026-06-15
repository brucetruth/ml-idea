# ml-idea

[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%208.2-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/github/license/brucetruth/ml-idea.svg)](LICENSE)

`ml-idea` is a modern, production-oriented machine learning library for PHP focused on clean APIs,
strict typing, and practical classification workflows.

Others always look down on PHP & have proclaimed its end since 2000, well, the elephant keeps moving.

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
- Probability calibration + threshold optimization: `CalibratedClassifierCV` (CV + `cv='prefit'`), `ThresholdTuner`, isotonic regression
- Regression support (`LinearRegression`, `RegressionMetrics`)
- Advanced modules: `PCA`, `KMeans`, `MiniBatchKMeans`, `DBSCAN`, sparse `TfidfVectorizer`
- Tree ensembles: `RandomForestClassifier/Regressor`, `GradientBoostingClassifier/Regressor`, `LinearSVC`, `DecisionTree`
- Model selection: stratified `GridSearchClassifier`, `RandomizedSearchClassifier` (accuracy, F1, ROC-AUC, PR-AUC)
- Pipeline persistence: `PipelineSerializer`, `TabularPipelineClassifier` (OneHot + scalers + estimator)
- Vision module: DCT/noise/patch forensics, `ForensicsVisionEmbedder`, `OllamaVisionEmbedder`, `VisionIndexer`, `VisionEval` (ROC-AUC), trainable `AuthenticityClassifier`, neural hooks
- Vision heuristics: color palette analysis, skin-tone risk, AI-generation authenticity scoring
- NLP foundation (Phase 1): fluent Text API, unicode tokenization with offsets, PII redaction, rule-based POS tagging
- NLP Phase 2: language detection, keyword extraction (RAKE), BM25 retrieval, hashing vectorizer, similarity utilities, and NLP RAG helpers
- NLP advanced tagging: multilingual rule-based POS, extensible language profiles, rule-based NER, spaCy-style `Nlp::load()` API (104 languages)
- NLP neural backend hooks: `CallableNlpBackend`, `OllamaNlpBackend`, `HuggingFaceInferenceBackend`
- GEO service + ML-GEO helpers: country/state/city lookup, nearest-place search, and geo feature building
- Managed dataset assets: registry, integrity checks, licenses metadata, and compiled indexes (trie/automaton/kd-tree)
- RAG foundations: embedders (`EmbedderFactory::fromEnv`, `OpenAI`, `AzureOpenAI`, `Ollama`, `HuggingFaceEmbedder`, `TeiEmbedder`, `HashEmbedder`), `VisionPathEmbedder`, splitters, retriever, vector stores
- RAG LLM clients for QA generation: `Echo`, `OpenAI`, `Azure OpenAI`, and `Ollama` (direct or `LlmClientFactory::fromEnv()`)
- Advanced RAG workflow: document loaders, hybrid retrieval, rerankers, citations/diagnostics, vector-index persistence, tool-calling + streaming hooks
- AI agents + tool routing: `ToolCallingAgent`, `ToolRoutingAgent`, deterministic/local routing, and provider-backed routing (OpenAI/Azure/Anthropic/Ollama/custom)
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
- ML competitiveness demo (`examples/33_ml_competitiveness.php`) — trees, pipelines, search, calibration
- Tier-2 ML/RAG demo (`examples/34_tier2_ml_rag.php`) — sparse TF-IDF, KMeans/DBSCAN, multiclass calibration, ANN
- Vision ML classifier demo (`examples/35_vision_ml_classifier.php`) — forensics features + trainable authenticity model
- Vision eval demo (`examples/36_vision_eval_demo.php`) — ROC-AUC/PR-AUC on labeled fixtures
- Vision/RAG frontier hooks (`examples/37_vision_rag_frontier_hooks.php`) — neural backends, HF embedder, vec0 factory
- Image similarity RAG (`examples/38_image_similarity_rag.php`) — forensics embeddings + ANN search
- Production embedder/index (`examples/39_production_embedder_and_vision_index.php`) — `EmbedderFactory`, `VisionIndexer`, directory scan
- Image similarity RAG (`examples/38_image_similarity_rag.php`) — forensics embeddings + ANN search

## Roadmap

### Priority 0 — Competitiveness Foundations

- Performance + benchmarking first: reproducible benchmark suite, memory/latency tracking, and publishable baseline reports.
- Production inference contract: versioned model bundles, input/output schema validation, safer deserialization, and deterministic fallback behavior.
- Reproducibility workflow: run metadata capture (params, seed, metrics, artifacts) and standardized experiment summaries.

### Priority 1 — Algorithm & Feature Coverage

- More algorithms (tree-based models, multiclass linear models, stronger ensemble baselines).
- Feature preprocessing (normalization, encoding, imputation, outlier-aware transforms, rare-category handling).
- Time-series ML support beyond splitting (lag/rolling feature generators and leakage-safe pipeline patterns).
- Cross-validation utilities expansion (task-specific helpers and richer evaluation modes).

### Priority 2 — Interoperability & Ecosystem

- Model interoperability bridges (portable formats/import-export adapters) for cross-runtime workflows.
- Dataset loaders and richer benchmarking tools.
- Documentation expansion: task-first recipes, performance tuning guides, and production deployment playbooks.

### Priority 3 — RAG/Agent Production Hardening

- Context and chat history handling for the Tool Routing Agent. *(v1.5: `AgentContextManager`)*
- Tool reliability layer for agents (timeouts, retries, fallbacks, structured errors). *(v1.5: `ToolReliabilityPolicy`, `RetryableToolInterface`)*
- Policy and safety guardrails (tool allow/deny rules, injection checks, PII-safe logs).
- Improved routing quality (confidence scoring, clarification turn, top-k tool candidates). *(v1.5: decision `confidence` field)*
- Observability + evaluation harness for routing/tool accuracy regressions. *(v1.5: `AgentEvalHarness`)*
- Streaming agent runs and human-in-the-loop approval gates. *(v1.6: `chatStream()`, `resumeWithApproval()`)*
- Session auto-persist for multi-turn agents. *(v1.6: `chatInSession()`, `AnthropicToolRoutingModel`, Ollama native tools)*
- MCP remote tools + pluggable session stores (file default, Redis optional). *(v1.7: `McpToolProvider`, `AgentStateStoreFactory`)*
- Multi-agent supervisor handoffs to specialist agents. *(v1.7: `AgentHandoffRegistry`, `handoff` decision)*
- OpenTelemetry-style tracing for agent runs. *(v1.7: `AgentTracerInterface`, `OpenTelemetryAgentTracer`)*
- Laravel bridge package with config, facade, and eval Artisan command. *(v1.7: `brucetruth/ml-idea-laravel`)*
- AI admin example: standalone demos in `examples/ai-admin/`, Laravel copy-paste demo in `packages/laravel/examples/ai-admin/`
- Memory strategy beyond raw history (summaries, pruning, retrieval-based recall).
- Cost/latency controls (model tiering, caching, token budgets).
- Human-in-the-loop controls for risky actions and execution approvals.
- Output quality controls (schema validation, grounding/citation checks, consistency pass).

### Strategic Positioning

- Position `ml-idea` as: **the production PHP AI runtime** combining classical ML + NLP + RAG + tool-using agents with strict typing and testability.
