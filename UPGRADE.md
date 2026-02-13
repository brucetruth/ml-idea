# Upgrade Guide

## From legacy `core/` to modern `src/` (v1)

This project has been modernized into a strict, production-oriented API under `src/`.

### What changed

- **Namespace root remains** `ML\\IDEA\\`, but classes now autoload from `src/`.
- Legacy classes in `core/` are now considered **deprecated/legacy**.
- Minimum PHP version is now **8.2**.
- Classifier APIs are standardized via `ClassifierInterface`:
  - `train(array $samples, array $labels): void`
  - `predict(array $sample): int|float|string|bool`
  - `predictBatch(array $samples): array`

### Recommended migration path

1. Update your runtime to PHP 8.2+.
2. Switch imports/usages to classes in `src/`, for example:
   - `ML\\IDEA\\Classifiers\\KNearestNeighbors`
   - `ML\\IDEA\\Classifiers\\LogisticRegression`
   - `ML\\IDEA\\Classifiers\\GaussianNaiveBayes`
3. Replace manual split/eval logic with:
   - `ML\\IDEA\\Data\\TrainTestSplit`
   - `ML\\IDEA\\Metrics\\ClassificationMetrics`
4. Use preprocessing transformers where needed:
   - `ML\\IDEA\\Preprocessing\\StandardScaler`
   - `ML\\IDEA\\Preprocessing\\MinMaxScaler`
5. Use model persistence through:
   - `ML\\IDEA\\Model\\ModelSerializer`

### Legacy code policy

- `core/` remains temporarily for backward reference only.
- New features and fixes should target `src/`.
- A future major version may remove `core/` entirely.
