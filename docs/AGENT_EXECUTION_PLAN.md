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

### v1.7 — Scale & ecosystem

| Deliverable | Status |
|---|---|
| MCP tool adapter (`McpToolProvider`, `McpRemoteTool`) | Shipped |
| File session store (`FileAgentStateStore`, default) | Shipped |
| Redis session store with auto file fallback (`AgentStateStoreFactory`) | Shipped |
| Multi-agent handoffs (`AgentHandoffRegistry`, `handoff` decision type) | Shipped |
| OpenTelemetry spans for iterations and tool calls | Planned |
| Laravel bridge package | Planned |

---

## 1) Context & memory

### Deliverables

- `AgentContextManager` with configurable routing window and tool message compression.
- Future: episodic memory store, semantic recall, LLM summarization hook.

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

- Parallel independent `tool_calls`
- Circuit breaker per tool
- Fallback tool chains

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

## 7) Observability (v1.7)

### Deliverables

- PSR-3 structured logging for agent runs
- OpenTelemetry span per iteration/tool call
- `AgentRunRepository` for audit trails

### Acceptance criteria

- Every run exports trace IDs linkable to `decisions` and `events`.
- No secrets in exported logs (reuse `TraceRedactor`).

---

## Definition of done (agents)

- Implementation + PHPUnit tests + example (when user-facing) + changelog note.
- Eval harness entry for routing behavior changes.
- No regression in default 128MB PHPUnit memory budget.
