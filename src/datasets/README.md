# Dataset seeds (optional)

Minimal seed files for CI and fresh clones. **Full bundled datasets live in `src/Dataset/`** and take precedence when both exist (larger file wins).

Code resolves via `DatasetPaths::resolve()` — checks `src/Dataset` first, then `src/datasets`, preferring the larger export.

Expected layout:

```
datasets/
  wordnet/wn.json
  sentiment/sentiment_dataset.json
  dictionary/en/en.csv
  dictionary/bemba/english_to_bemba.csv
  geo/countries.json
  geo/countries+states.json
  geo/cities.json
```

Integrity check:

```bash
php -r "require 'vendor/autoload.php'; print_r((new ML\IDEA\Dataset\Registry\DatasetRegistry())->integrityReport());"
```

Replace the minimal seed files here with full exports when available.
