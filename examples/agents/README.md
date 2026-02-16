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
php examples/agents/07_custom_rag_tool_demo.php
php examples/agents/08_custom_tool_routing_model_demo.php
php examples/agents/09_custom_embedder_demo.php
php examples/agents/10_custom_llm_client_demo.php
php examples/agents/04_db_query_tool_demo.php
# optional provider-backed routing:
# AGENT_PROVIDER=openai OPENAI_API_KEY=... php examples/agents/03_tool_routing_agent_providers.php
# AGENT_PROVIDER=azure AZURE_OPENAI_API_KEY=... AZURE_OPENAI_ENDPOINT=... AZURE_OPENAI_CHAT_DEPLOYMENT=... php examples/agents/03_tool_routing_agent_providers.php
# AGENT_PROVIDER=ollama OLLAMA_MODEL=llama3.1 php examples/agents/03_tool_routing_agent_providers.php
# RAG_LLM_PROVIDER=openai OPENAI_API_KEY=... OPENAI_CHAT_MODEL=gpt-4o-mini php examples/09_rag_local_inmemory.php
# RAG_LLM_PROVIDER=azure AZURE_OPENAI_API_KEY=... AZURE_OPENAI_ENDPOINT=... AZURE_OPENAI_CHAT_DEPLOYMENT=... php examples/11_rag_hybrid_agent_streaming.php
# RAG_LLM_PROVIDER=ollama OLLAMA_MODEL=llama3.1 php examples/12_rag_db_loader_sqlite.php
# CLAUDE_API_KEY=... CLAUDE_MODEL=claude-3-5-sonnet-20240620 php examples/agents/08_custom_tool_routing_model_demo.php
# CLAUDE_API_KEY=... CLAUDE_LLM_MODEL=claude-3-5-sonnet-20240620 php examples/agents/09_custom_embedder_demo.php
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

## Customization examples

- `07_custom_rag_tool_demo.php`
  - shows how to implement a custom `ToolInterface` tool and wire it into `ToolRoutingAgent`
- `08_custom_tool_routing_model_demo.php`
  - shows a custom Claude-style `ToolRoutingModelInterface` with API key + model env configuration
- `09_custom_embedder_demo.php`
  - shows a custom `EmbedderInterface`, custom `QueryExpanderInterface`, and optional Claude-style `LlmClientInterface` for `RetrievalQAChain`
- `10_custom_llm_client_demo.php`
  - shows a minimal custom `LlmClientInterface` implementation for full control over generation behavior

## Agent identity + system features customization

Both `ToolRoutingAgent` and `ToolCallingAgent` now support optional prompt customization fields:

- `agentName` (agent identity)
- `agentFeatures` (extra behavior/features in system prompt)
- `systemPrompt` (full prompt override)

See `02_tool_routing_agent_local.php` and `01_local_agent_toolbox_demo.php` for concrete usage.

## Built-in LLM clients for RetrievalQAChain

You can select built-in QA LLM clients via `RAG_LLM_PROVIDER` in examples that call `LlmClientFactory::fromEnv()`:

- `echo` (default, deterministic local stub)
- `openai` (`OPENAI_API_KEY`, optional `OPENAI_CHAT_MODEL`)
- `azure` (`AZURE_OPENAI_API_KEY`, `AZURE_OPENAI_ENDPOINT`, `AZURE_OPENAI_CHAT_DEPLOYMENT`)
- `ollama` (`OLLAMA_MODEL`, optional `OLLAMA_BASE_URL`)

You can use them in two ways:

1) **With env factory**

```php
use ML\IDEA\RAG\LLM\LlmClientFactory;
$llm = LlmClientFactory::fromEnv();
```

2) **Without env factory (direct constructor)**

```php
use ML\IDEA\RAG\LLM\OpenAILlmClient;
$llm = new OpenAILlmClient('YOUR_OPENAI_API_KEY', 'gpt-4o-mini');
```

The same direct-constructor pattern also applies to `AzureOpenAILlmClient` and `OllamaLlmClient`.

