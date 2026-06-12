# Competitiveness Execution Plan

This plan converts the roadmap priorities into concrete, shippable workstreams with acceptance criteria.

## 1) Performance + Benchmarking

### Deliverables
- Add benchmark harness with reproducible dataset seeds and fixed run protocol.
- Add benchmark commands to run micro (math/kernel) and macro (fit/predict pipeline) tests.
- Publish baseline reports (latency + memory) for key estimators and RAG primitives.

### Initial scope
- Models: KNN, LogisticRegression, GaussianNaiveBayes, RandomForestClassifier, LinearRegression.
- Workflows: scaler+classifier pipeline, train/test split flow, serialization/deserialization path.

### Acceptance criteria
- Benchmarks run deterministically on PHP 8.2+ with fixed seeds.
- Reports include p50/p95 latency and peak memory.
- CI job fails on configurable regression thresholds.

## 2) Multiclass / Boosting Algorithm Coverage

### Deliverables
- Multiclass logistic regression (softmax) with one-vs-rest fallback strategy.
- Gradient Boosting (classifier first, regressor second) with sane defaults and early stopping.
- Unified hyperparameter exposure via existing param contracts.

### Initial scope
- Implement estimator classes + tests + example scripts.
- Add metrics support for multiclass confusion matrix summaries if gaps exist.

### Acceptance criteria
- New models pass convergence + correctness tests on synthetic and real toy datasets.
- API parity with existing classifiers (`train`, `predict`, `predictBatch`, optional proba).
- Included in benchmark matrix and model persistence checks.

## 3) Production Inference Contract + Reproducibility

### Deliverables
- Versioned model bundle manifest (model class, schema hash, params, training metadata).
- Input/output schema validator for inference-time payloads.
- Reproducibility recorder: seed, dataset fingerprint, split strategy, metrics, environment snapshot.

### Initial scope
- Extend model serialization pipeline and contracts.
- Add strict validation errors with actionable diagnostics.

### Acceptance criteria
- Loading incompatible bundle versions produces clear failure modes.
- Inference rejects schema mismatch before model invocation.
- Re-running with same seed and data fingerprint produces stable metrics within tolerance.

## 4) Time-series + Advanced Preprocessing

### Deliverables
- Time-series feature generators: lag, rolling mean/std/min/max, calendar decomposition helpers.
- Leakage-safe pipeline recipe and helper utilities for temporal train/validation boundaries.
- Advanced preprocessors: robust scaler, rare-category encoder, missing-indicator support.

### Initial scope
- Start with univariate/multivariate tabular time-series transformations.
- Add examples for forecasting-style supervised framing.

### Acceptance criteria
- Time-series helpers integrate with existing pipeline abstractions.
- Unit tests verify no future leakage in generated features.
- New preprocessors support fit/transform contract and serialization where applicable.

## 5) Interoperability + Enterprise Hardening

### Deliverables
- Interop adapters: portable model export/import format (JSON-first), ONNX gateway feasibility layer.
- Security posture: `SECURITY.md`, vulnerability disclosure workflow, signed release checklist.
- Stability guarantees: semantic versioning policy and compatibility matrix.

### Initial scope
- Define minimal interoperable schema for linear/tree-style predictors first.
- Add upgrade/migration notes for breaking changes.

### Acceptance criteria
- Round-trip export/import tests for supported model subset.
- Documented support policy and deprecation windows.
- Release process includes security and compatibility checks.

## Suggested release train

- **v1.next (Foundation):** benchmarking harness, reproducibility metadata, inference schema checks.
- **v1.next+1 (Coverage):** multiclass logistic + first boosting model + advanced preprocessing tranche.
- **v1.next+2 (Production):** time-series toolkit, interop adapters, enterprise hardening docs/process.

## Definition of done (cross-cutting)

- Feature includes: implementation, tests, docs, examples, benchmark entry, and changelog note.
- CI includes correctness + static analysis + benchmark regression gate for affected modules.
