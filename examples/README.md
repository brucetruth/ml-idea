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
   - probability calibration (`CalibratedClassifierCV`) + `ThresholdTuner`

4. `04_pipeline_regression_with_poly.php`
   - regression pipeline + polynomial features + RMSE / MAE / MAPE

5. `05_text_features_and_clustering.php`
   - TF-IDF + PCA + MiniBatchKMeans on small text-like corpus

6. `06_grid_search_model_selection.php`
   - hyperparameter optimization with `GridSearchClassifier`

7. `07_model_save_load_classifier.php`
   - train, save, load, and infer with a classifier + sidecar metadata JSON

8. `08_pipeline_persistence_with_metadata.php`
   - practical pipeline-bundle persistence pattern:
     model artifact + preprocessing stats + serving contract metadata

9. `09_rag_local_inmemory.php`
   - local end-to-end RAG chain (hash embeddings + in-memory vector store)

10. `10_rag_vectorstores_json_sqlite.php`
   - compare JSON-file and SQLite vector stores with same retrieval flow

11. `11_rag_hybrid_agent_streaming.php`
   - hybrid-style retrieval orchestration + tool-calling agent + streaming output

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

17. `13_vision_palette_extraction.php`
   - generic image feature pipeline used to extract dominant color palette

18. `14_vision_content_risk_demo.php`
   - generic image analysis pipeline for skin-tone risk heuristics + palette summary

19. `15_vision_authenticity_risk_demo.php`
   - heuristic AI-generation authenticity-risk analysis from metadata + visual signals

20. `16_nlp_text_api_and_pos.php`
   - fluent Text API demo (normalization, PII masking, tokenization) + POS tagging baseline

21. `17_nlp_bm25_and_similarity.php`
   - BM25 retrieval demo + hashing vectorization + cosine similarity

22. `18_nlp_multilingual_ner.php`
   - multilingual POS tagging + rule-based NER with custom gazetteer

23. `19_nlp_extensibility_custom_profiles.php`
   - extending language profiles and POS lexicon customization

24. `20_nlp_trainable_pos_ner.php`
   - trainable perceptron-style POS and NER pipeline usage

25. `21_geo_service_and_ner.php`
   - GEO dataset wrapper usage + geo-aware NER gazetteer integration

26. `22_sentiment_and_translation.php`
   - sentiment analyzer training/inference + English-to-Bemba dictionary translation

27. `23_ml_geo_foundation.php`
   - geo feature building + simple ML classification baseline

28. `24_nlp_semantic_explorer.php`
   - bi-directional semantics: word→synonyms/definition and meaning→matching words

29. `25_dataset_registry_and_indexes.php`
   - managed dataset registry, integrity report, and compiled index usage

30. `26_ner_gazetteer_geo_aware.php`
   - Aho-Corasick gazetteer NER + alias handling + geo-aware disambiguation flow

31. `27_geo_chunked_index_build.php`
   - chunked geo index compile + file-persisted cache for low-memory reuse

32. `28_hyperparams_and_random_state.php`
   - contract helpers demo: `fit`, `getParams`, `setParams`, `cloneWithParams`, `setRandomState`

## Artifacts

Some examples create files under `examples/artifacts/`.
These can be safely deleted anytime.
