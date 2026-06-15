# Examples

This folder showcases practical and edge-case workflows for `ml-idea`.

## Run an example

From project root:

```bash
php examples/01_basic_classification.php
```

## Included examples

1. `01_basic_classification.php`
   - baseline classification with train/test split + scaling + accuracy

2. `02_cross_validation_and_metrics.php`
   - KFold CV + advanced classification metrics (`rocAuc`, `prAuc`, `logLoss`, `mcc`)

3. `03_probability_calibration_and_threshold_tuning.php`
   - probability calibration (`CalibratedClassifierCV` with CV and `cv='prefit'`) + `ThresholdTuner`

4. `04_pipeline_regression_with_poly.php`
   - regression pipeline + polynomial features + RMSE / MAE / MAPE

5. `05_text_features_and_clustering.php`
   - TF-IDF (optional sparse via `TFIDF_SPARSE=1`) + PCA + full `KMeans` (default) or `MiniBatchKMeans`

6. `06_grid_search_model_selection.php`
   - hyperparameter optimization with `GridSearchClassifier` (stratified CV by default)

7. `07_model_save_load_classifier.php`
   - train, save, load, and infer with a classifier + sidecar metadata JSON

8. `08_pipeline_persistence_with_metadata.php`
   - practical pipeline-bundle persistence pattern:
     model artifact + preprocessing stats + serving contract metadata

9. `09_rag_local_inmemory.php`
   - local end-to-end RAG chain (hash embeddings + **AnnVectorStore** with exact fallback + **WordNet query expansion**)

10. `10_rag_vectorstores_json_sqlite.php`
   - compare JSON-file, SQLite, **AnnVectorStore**, and persisted **SQLiteAnnVectorStore** (IVF ANN with exact fallback on small corpora)

11. `11_rag_hybrid_agent_streaming.php`
   - hybrid-style retrieval + **WordNet expansion** + tool-calling agent + streaming output

12. `12_rag_db_loader_sqlite.php`
   - loads documents from SQLite via `PdoLoader` and runs RAG QA

13. `agents/01_local_agent_toolbox_demo.php`
   - agent demo with local knowledge base + weather tool + free API tool

14. `agents/02_tool_routing_agent_local.php`
   - deterministic local tool-routing agent (`rag_qa`, `weather`, `math`)

15. `agents/03_tool_routing_agent_providers.php`
   - provider-backed tool-routing agent with OpenAI / Azure OpenAI / Ollama

16. `agents/04_db_query_tool_demo.php`
   - read-only DB query tool demo with routing agent + math tool

17. `agents/07_custom_rag_tool_demo.php`
   - custom `ToolInterface` implementation wired into `ToolRoutingAgent`

18. `ai-admin/` (folder)
   - AI admin assistant with six custom tools (users, orders, ban, refund)
   - standalone demos; Laravel copy-paste files under `packages/laravel/examples/ai-admin/`
   - run: `php examples/ai-admin/run_admin_agent.php "List all users"`

19. `agents/08_custom_tool_routing_model_demo.php`
   - custom `ToolRoutingModelInterface` implementation (provider/router customization)

20. `agents/09_custom_embedder_demo.php`
   - custom `EmbedderInterface` + custom query expansion in a retrieval chain

21. `agents/10_custom_llm_client_demo.php`
   - custom `LlmClientInterface` implementation for RetrievalQAChain

21. `13_vision_palette_extraction.php`
   - generic image feature pipeline used to extract dominant color palette

22. `14_vision_content_risk_demo.php`
   - generic image analysis pipeline for skin-tone risk heuristics + palette summary

23. `15_vision_authenticity_risk_demo.php`
   - heuristic AI-generation authenticity-risk analysis from metadata + visual signals

24. `16_nlp_text_api_and_pos.php`
   - fluent Text API demo (normalization, PII masking, tokenization) + POS tagging baseline

25. `17_nlp_bm25_and_similarity.php`
   - BM25 retrieval demo + hashing vectorization + cosine similarity

26. `18_nlp_multilingual_ner.php`
   - multilingual POS tagging + rule-based NER with custom gazetteer

27. `19_nlp_extensibility_custom_profiles.php`
   - extending language profiles and POS lexicon customization

28. `20_nlp_trainable_pos_ner.php`
   - trainable perceptron-style POS and NER pipeline usage

29. `21_geo_service_and_ner.php`
   - GEO dataset wrapper usage + geo-aware NER gazetteer integration

30. `22_sentiment_and_translation.php`
   - sentiment analyzer training/inference + English-to-Bemba dictionary translation

31. `23_ml_geo_foundation.php`
   - geo feature building + simple ML classification baseline

32. `24_nlp_semantic_explorer.php`
   - bi-directional semantics using bundled WordNet + dictionary datasets by default

33. `25_dataset_registry_and_indexes.php`
   - managed dataset registry, integrity report, and compiled index usage

34. `26_ner_gazetteer_geo_aware.php`
   - Aho-Corasick gazetteer NER + alias handling + geo-aware disambiguation flow

35. `27_nlp_pipeline_demo.php`
   - `NlpPipeline`, `LanguagePipelineFactory`, WordNet RAG expander, extended `Text` API, hashing embeddings

36. `27_geo_chunked_index_build.php`
   - chunked geo index compile + file-persisted cache for low-memory reuse

37. `28_hyperparams_and_random_state.php`
   - contract helpers demo: `fit`, `getParams`, `setParams`, `cloneWithParams`, `setRandomState`

38. `28_nlp_tier3_stemming_embeddings_translation.php`
   - English normalization/stemming, BM25 with normalization, hashing embeddings bridged to RAG, Bemba translation coverage

39. `29_nlp_eval_demo.php`
   - `NlpEval` helpers for POS/NER/sentiment/language-detection quality checks on golden samples

40. `29_multiclass_logistic_regression.php`
   - multiclass logistic regression baseline (ML classifier, not NLP-specific)

## ML competitiveness (sklearn-grade tabular ML)

```bash
php examples/33_ml_competitiveness.php
```

44. `33_ml_competitiveness.php`
   - real CART-based `RandomForest` / `GradientBoosting` / `GradientBoostingRegressor` with `predictProba`
   - `LinearSVC`, mini-batch `LogisticRegression`, `TabularPipelineClassifier` + `OneHotEncoder`
   - `PipelineSerializer` save/load, stratified `GridSearchClassifier` + `RandomizedSearchClassifier`
   - isotonic `CalibratedClassifierCV`, `PermutationImportance`, `ClusteringMetrics::silhouetteScore`

## Tier-2 ML/RAG (sparse, ANN, clustering, multiclass calibration)

```bash
php examples/34_tier2_ml_rag.php
```

45. `34_tier2_ml_rag.php`
   - sparse TF-IDF output + `densify()` for downstream ML
   - full `KMeans`, `DBSCAN`, multiclass `CalibratedClassifierCV`
   - `AnnVectorStore` (IVF approximate nearest-neighbor index)
   - `OllamaNlpBackend`, `HuggingFaceInferenceBackend`, `CallableNlpBackend` wiring

46. `35_vision_ml_classifier.php`
   - trainable `AuthenticityClassifier` on forensics features + `LogisticRegression`
   - persistence via `toArray()` / `fromArray()`

47. `36_vision_eval_demo.php`
   - `VisionEval` ROC-AUC / PR-AUC on labeled authenticity JSON fixtures

48. `37_vision_rag_frontier_hooks.php`
   - `CallableVisionBackend`, `CallableVisionEmbedder`, `HuggingFaceEmbedder`, `VectorStoreFactory` (sqlite-vec → ANN fallback)

49. `38_image_similarity_rag.php`
   - `ForensicsVisionEmbedder` + `VisionPathEmbedder` + `AnnVectorStore` image similarity search on synthetic GD images

50. `39_production_embedder_and_vision_index.php`
   - `EmbedderFactory::fromEnv()` (hash/openai/ollama/tei/huggingface), `VisionIndexer`, directory scan, `OllamaVisionEmbedder`

## International NLP & spaCy-style API (104 languages)

These three examples cover multilingual detection, the `Nlp::load()` model registry, composable pipelines, and HF-style backend hooks.

```bash
php examples/33_ml_competitiveness.php
php examples/34_tier2_ml_rag.php
php examples/35_vision_ml_classifier.php
php examples/36_vision_eval_demo.php
php examples/37_vision_rag_frontier_hooks.php
php examples/38_image_similarity_rag.php
php examples/39_production_embedder_and_vision_index.php
php examples/30_multilingual_and_spacy_style_api.php
php examples/31_international_languages.php
php examples/32_spacy_style_pipeline.php
```

41. `30_multilingual_and_spacy_style_api.php`
   - mixed-language detection (`languageMixed`, `languageSegments`, `languageTop`)
   - `Nlp::load('en_core')`, `Nlp::models()`, `Doc` tokens/entities/sents
   - batch processing with `$nlp->pipe([...])`

42. `31_international_languages.php`
   - `Nlp::languageCount()`, `languageNames()`, `languagesByScript()`, `languagesByFamily()`
   - detection samples across regions (Europe, Asia, Africa, Middle East)
   - mixed documents (e.g. English + French + Japanese)

43. `32_spacy_style_pipeline.php`
   - default pipe order: `language → tokenizer → tagger → ner → sents`
   - `pipeNames()`, `disablePipes()` for lighter/faster runs
   - `Doc::spans()`, `Doc::toJson()`
   - `CallableNlpBackend` as a hook for Hugging Face / Ollama / custom neural backends
   - use `HuggingFaceInferenceBackend` for HF Inference API NER

### Quick API reference

| Goal | Code |
|------|------|
| Load a model | `Nlp::load('en_core')` or `Nlp::load('ja_core')` |
| List languages | `Nlp::languages()`, `Nlp::languageNames()` |
| Detect language | `Text::of($text)->languageWithScore()` |
| Code-switching | `Text::of($text)->languageMixed()` |
| Process text | `$nlp->process($text)` → `Doc` |
| Batch | `$nlp->pipe([$text1, $text2])` |
| Skip steps | `$nlp->disablePipes(['ner', 'tagger'])` |
| Neural backend | `Nlp::blank('en')->withBackend(new HuggingFaceInferenceBackend('dslim/bert-base-NER', $token))` |

Named models include `en_core`, `multilingual`, `de_core`, `pt_core`, `ar_core`, `hi_core`, `ru_core`, `ja_core`, `ko_core`, `zh_core`, `sw_core`, `zambia-bem`, and `zambia-nya`.

## Artifacts

Some examples create files under `examples/artifacts/`.
These can be safely deleted anytime.
