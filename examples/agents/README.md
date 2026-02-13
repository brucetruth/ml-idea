# Agent Examples

This folder demonstrates how to compose a simple local agent with tools:

- local knowledge base access via `rag_qa`
- weather tool (`weather`)
- geo resolver tool (`geo_resolver`)
- free API GET tool (`free_api`)
- advanced math tool (`math`)
- LLM-driven tool routing agent with OpenAI / Azure OpenAI / Ollama backends

Run:

```bash
php examples/agents/01_local_agent_toolbox_demo.php
php examples/agents/02_tool_routing_agent_local.php
php examples/agents/05_local_document_explorer_demo.php
php examples/agents/06_doc_explorer_tools_demo.php
php examples/agents/04_db_query_tool_demo.php
# optional provider-backed routing:
# AGENT_PROVIDER=openai OPENAI_API_KEY=... php examples/agents/03_tool_routing_agent_providers.php
# AGENT_PROVIDER=azure AZURE_OPENAI_API_KEY=... AZURE_OPENAI_ENDPOINT=... AZURE_OPENAI_CHAT_DEPLOYMENT=... php examples/agents/03_tool_routing_agent_providers.php
# AGENT_PROVIDER=ollama OLLAMA_MODEL=llama3.1 php examples/agents/03_tool_routing_agent_providers.php
```

## DB Query Tool safety

`DbQueryTool` is designed to be safe-by-default:

- read-only mode enabled by default
- single statement enforcement (no `;` chaining)
- table allow-list support
- optional column allow-list policy per table
- row limit cap
- execution budget guard (ms threshold)
- parameterized query execution (`params`)
- JSONL-style audit logging to file

## Local Document Explorer (no LLM / no DB)

`05_local_document_explorer_demo.php` showcases a fully local, deterministic document explorer:

- source registry (`addSourceText`, optional file ingestion)
- fixed chunking with offsets
- BM25 indexing and search
- regex/entity extraction
- extractive summaries with citations
- glossary generation
- output PII redaction

This is useful for “explore your data” workflows over files/notes/KB text without external APIs or database queries.

## Performance notes

- `01_local_agent_toolbox_demo.php` defaults to a fast, deterministic offline mode for `weather` and `free_api` calls.
  - Use live APIs by setting: `MLIDEA_EXAMPLE_OFFLINE=0`
- `05_local_document_explorer_demo.php` and `06_doc_explorer_tools_demo.php` reuse persisted local indexes under
  `examples/artifacts/doc_explorer_cache` to avoid rebuilding on every run.

