# Agent Execution Plan

This plan turns the Priority 3 agent roadmap into shippable workstreams for `ToolRoutingAgent` and related infrastructure.

## Strategic goal

Position **ml-idea** as the production PHP runtime for agentic AI: typed tool routing, RAG integration, budgets, policy, offline testability, and framework-friendly ergonomics.

## Release train

### v1.5 — Agent foundation (current)

| Deliverable | Status |
|---|---|
| `AgentContextManager` — message windowing + tool output compression | Shipped |
| `ToolReliabilityPolicy` — retries for idempotent tools, soft timeout | Shipped |
| `AgentEvalHarness` — routing regression fixtures + pass rate summary | Shipped |
| Planner prompt wired into `ToolRoutingAgent` system prompt | Shipped |
| `RetryableToolInterface` for idempotent tools | Shipped |

### v1.6 — Interaction & provider parity

| Deliverable | Status |
|---|---|
| Streaming agent events (`chatStream()`) | Shipped |
| `StreamingToolRoutingModelInterface` for token streaming | Shipped |
| HITL pause/resume (`awaiting_approval`, `resumeWithApproval()`) | Shipped |
| Ollama native tool calling + Anthropic routing model | Shipped |
| Optional auto-persist via `AgentStateStoreInterface` on agent constructor | Shipped |

### v1.9 — Safety & memory

| Deliverable | Status |
|---|---|
| `IdempotentToolInterface` + `ToolIdempotencyStoreInterface` | Shipped |
| `FileToolIdempotencyStore` / `InMemoryToolIdempotencyStore` | Shipped |
| `AgentMemoryStoreInterface` episodic recall | Shipped |
| `FileAgentMemoryStore` / `InMemoryAgentMemoryStore` | Shipped |
| `ToolCallBatchPlanner` — order multi-tool batches low→high risk | Shipped |
| Laravel config: `idempotency`, `memory`, `order_tool_calls_by_risk` | Shipped |
| `refund_order` implements idempotency key | Shipped |

### v2.0 — Scale & resilience (current)

| Deliverable | Status |
|---|---|
| `EpisodicMemorySummarizerInterface` + `TruncatingEpisodicMemorySummarizer` | Shipped |
| `LlmEpisodicMemorySummarizer` — LLM-backed session recall | Shipped |
| `ToolCircuitBreaker` wired into `ToolExecutor` | Shipped |
| `ParallelInvokableToolInterface` + `ParallelToolCallRunner` (ext-parallel) | Shipped |
| `MathTool::invokeParallel()` for worker-safe execution | Shipped |
| Laravel config: `memory.summarizer`, `circuit_breaker`, `parallel_tools` | Shipped |
| Demos: `20_llm_memory_demo.php`, `21_circuit_breaker_demo.php`, `22_parallel_tools_demo.php` | Shipped |

### v1.8 — Production hooks

| Deliverable | Status |
|---|---|
| `CallbackAgentRunLogger`, `Psr3AgentRunLogger`, `MultiAgentRunLogger` | Shipped |
| Laravel events: `AgentRunCompleted`, `AgentAwaitingApproval` | Shipped |
| `docs/AGENT_COOKBOOK.md` | Shipped |
| Laravel budget config (`max_tokens`, `max_estimated_cost`, `max_runtime_ms`) | Shipped |
| SSE streaming controller example | Shipped |
| AI-admin eval fixture + `run_agent_eval.php` | Shipped |
| Logging drivers: `psr3`, `multi` | Shipped |
| `MlIdeaAgent::resumeWithApproval()` with event dispatch | Shipped |

### v1.7 — Scale & ecosystem

| Deliverable | Status |
|---|---|
| MCP tool adapter (`McpToolProvider`, `McpRemoteTool`) | Shipped |
| File session store (`FileAgentStateStore`, default) | Shipped |
| Redis session store with auto file fallback (`AgentStateStoreFactory`) | Shipped |
| Multi-agent handoffs (`AgentHandoffRegistry`, `handoff` decision type) | Shipped |
| OpenTelemetry spans for iterations and tool calls | Shipped |
| Laravel bridge package | Shipped |

---

## 1) Context & memory

### Deliverables

- `AgentContextManager` with configurable routing window and tool message compression.
- Future: episodic memory store, semantic recall, LLM summarization hook. *(v1.9: file/memory episodic store + context injection shipped; v2.0: LLM summarizer driver shipped)*

### Acceptance criteria

- Long sessions stay within routing message budget without losing system prompt or latest turns.
- Tool outputs longer than configured limit are truncated before model routing.
- Unit tests cover windowing and compression.

---

## 2) Tool reliability

### Deliverables

- `ToolReliabilityPolicy`: `maxAttempts`, `retryDelayMs`, `timeoutMs`.
- `RetryableToolInterface` for idempotent tools.
- Structured error types: `timeout`, `tool_exception`, `validation_error`, `policy_block`.

### Acceptance criteria

- Idempotent tools retry up to `maxAttempts` on transient exceptions.
- Duration exceeding `timeoutMs` yields `timeout` error type.
- Retries are visible in structured tool results (`attempts` field).

### Future

- True parallel `tool_calls` (requires optional async runtime)
- Circuit breaker per tool
- Fallback tool chains
- LLM-powered episodic summarization hook

---

## 3) Eval harness

### Deliverables

- `AgentEvalHarness` + `AgentEvalCase` for deterministic routing regression tests.
- JSON fixture loader for CI datasets.
- Metrics: stop reason, tool selection, answer contains/not contains, tool call counts.

### Acceptance criteria

- Harness runs offline with `HeuristicToolRoutingModel` or stub models.
- CI can fail on routing regression thresholds.
- Example script demonstrates eval workflow.

---

## 4) Streaming & HITL (v1.6)

### Deliverables

- `StreamingToolRoutingModelInterface`
- `ToolRoutingAgent::chatStream()` event generator
- `awaiting_approval` + `resumeWithApproval()`

### Acceptance criteria

- Consumers receive incremental events suitable for SSE/WebSocket.
- High-risk tool calls can pause, persist state, and resume after approval.

---

## 6) Multi-agent handoffs (v1.7)

### Deliverables

- `AgentHandoffRegistry` for named specialist agents
- `handoff` decision type in `AgentDecision`
- Supervisor loop delegates via `ToolRoutingAgent::executeHandoff()`
- Stream events: `handoff_start`, `handoff_result`

### Acceptance criteria

- Supervisor can delegate to a registered specialist and synthesize a final answer.
- Handoff metadata appears in `handoffs`, `decisions`, and `events`.
- Unknown targets fail gracefully without terminating the run.

---

## 9) Laravel bridge (v1.7)

### Deliverables

- `brucetruth/ml-idea-laravel` package under `packages/laravel`
- Auto-discovered `MlIdeaServiceProvider`
- Publishable `config/mlidea.php` (model, tools, store, tracing)
- `ToolRoutingAgentManager` + `MlIdeaAgent` facade
- `php artisan mlidea:agent-eval {fixture}`

### Acceptance criteria

- Laravel apps configure agents via env + config without manual wiring.
- Session store defaults to `storage/app/mlidea/agent-sessions`.
- Eval command exits non-zero when pass rate falls below threshold.

---

## 7) Observability (v1.7)

### Deliverables

- PSR-3 structured logging for agent runs
- `AgentTracerInterface` + `RecordingAgentTracer` + optional `OpenTelemetryAgentTracer`
- Spans: `agent.run`, `agent.iteration`, `agent.tool_call`, `agent.handoff`
- `telemetry` block on agent responses (`trace_id`, `span_id`)
- `AgentRunRepository` for audit trails (future)

Shipped in v1.7+: `AgentRunLoggerInterface`, `JsonlAgentRunLogger`, `DatabaseAgentRunLogger` (Laravel), config drivers `noop|jsonl|database`.

### Acceptance criteria

- Every run exports trace IDs linkable to `decisions` and `events`.
- No secrets in exported logs (reuse `TraceRedactor`).

---

## Definition of done (agents)

- Implementation + PHPUnit tests + example (when user-facing) + changelog note.
- Eval harness entry for routing behavior changes.
- No regression in default 128MB PHPUnit memory budget.
