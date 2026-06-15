# Agent Cookbook

Production patterns for **ml-idea** `ToolRoutingAgent` and the Laravel bridge.

## Pick your pattern

| Goal | Pattern | Example |
|---|---|---|
| Admin chat (natural language → tools) | `MlIdeaAgent::chat()` | `AdminAgentController` |
| Customer ticket → auto triage (no HITL) | Queue job + low-risk tools | `ProcessAutonomousSupportTicketJob` |
| Refund / ban with human gate | HITL + `AgentApprovalContext` | `AdminRefundApprovalController` |
| Audit every run to file | `MLIDEA_LOGGING_DRIVER=jsonl` | `18_agent_run_logger_demo.php` |
| Audit every run to DB | `MLIDEA_LOGGING_DRIVER=database` | `agent_runs_migration.example.php` |
| React to runs in Laravel | Listen to `AgentRunCompleted` | `LogAgentRunListener.example.php` |
| Streaming UI (SSE) | `MlIdeaAgent::chatStream()` | `AdminAgentStreamController.example.php` |
| CI routing regression | `AgentEvalHarness` + JSON fixtures | `mlidea:agent-eval`, `run_agent_eval.php` |
| Offline dev / tests | `HeuristicToolRoutingModel` or `AdminHeuristicRouter` | No API key required |

---

## 1. Custom tool (Laravel)

```php
final class ListUsersTool implements ToolInterface, ToolSchemaInterface
{
    public function __construct(private readonly UserRepository $users) {}

    public function name(): string { return 'list_users'; }
    public function riskLevel(): string { return 'low'; }

    public function invoke(array $input): string
    {
        return json_encode(['users' => $this->users->all()], JSON_THROW_ON_ERROR);
    }
    // + description(), inputSchema(), examples()
}
```

Register: `MlIdeaAgent::registerToolClass(ListUsersTool::class)` or `config/mlidea.php` `tools` array.

---

## 2. Risk levels + HITL

| Risk | Examples | `pause_for_approval=true` |
|---|---|---|
| low | read tools, notes, tags | runs immediately |
| medium | role change, ticket status | runs immediately |
| high | refund, ban | pauses → admin approves |

```env
MLIDEA_PAUSE_FOR_APPROVAL=true
```

Admin approves via `AgentApprovalContext::resume($manager, $approved)` — no raw `agent_state` in HTTP body.

---

## 3. Audit logging

```env
# one sink
MLIDEA_LOGGING_DRIVER=jsonl

# database (migrate agent_runs first)
MLIDEA_LOGGING_DRIVER=database

# both
MLIDEA_LOGGING_DRIVER=multi
MLIDEA_LOGGING_DRIVERS=jsonl,database

# Laravel log channel (PSR-3)
MLIDEA_LOGGING_DRIVER=psr3
```

Standalone:

```php
$logger = new JsonlAgentRunLogger('/path/runs.jsonl');
new ToolRoutingAgent($model, $tools, agentRunLogger: $logger);
```

Custom sink:

```php
new CallbackAgentRunLogger(static function (AgentRunLogEntry $entry): void {
    MyAuditService::record($entry->toArray());
});
```

---

## 4. Laravel events

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    \ML\IDEA\Laravel\Events\AgentRunCompleted::class => [
        \App\Listeners\LogAgentRun::class,
    ],
    \ML\IDEA\Laravel\Events\AgentAwaitingApproval::class => [
        \App\Listeners\NotifyAdminForApproval::class,
    ],
];
```

Disable: `MLIDEA_EVENTS_ENABLED=false`

Use `MlIdeaAgent::resumeWithApproval()` (facade) so completion events fire after approval.

---

## 5. Budgets

```env
MLIDEA_MAX_RUNTIME_MS=30000
MLIDEA_MAX_TOKENS=50000        # 0 = unlimited
MLIDEA_MAX_ESTIMATED_COST=1.50 # 0 = unlimited
```

Stop reasons: `runtime_budget_exceeded`, `token_budget_exceeded`, `cost_budget_exceeded`.

---

## 6. Eval in CI

```bash
php artisan mlidea:agent-eval tests/fixtures/agent_eval_heuristic.json --min-pass-rate=1.0
php examples/ai-admin/run_agent_eval.php
```

Fixtures assert: `stop_reason`, `tool_names`, `answer_contains`, `min_tool_calls`.

---

## 7. Session persistence

```env
MLIDEA_STORE_DRIVER=redis   # or file, auto
```

Same `session_id` across turns → agent remembers prior tool results.

---

## 8. Idempotency & episodic memory (v1.9)

Tools implementing `IdempotentToolInterface` declare `idempotencyKey()`. With a `ToolIdempotencyStore` wired into `ToolExecutor`, retries and HITL resume replay cached results instead of re-invoking side effects.

Episodic memory (`AgentMemoryStoreInterface`) records tool outcomes per session and injects a summary when routing context is windowed.

Laravel:

```env
MLIDEA_IDEMPOTENCY_DRIVER=file
MLIDEA_MEMORY_DRIVER=file
MLIDEA_ORDER_TOOL_CALLS_BY_RISK=true
```

Demos: `examples/agents/19_idempotency_demo.php`

---

## 9. LLM memory, circuit breaker, parallel tools (v2.0)

**LLM episodic summarization** — inject `EpisodicMemorySummarizerInterface` into memory stores. Use `LlmEpisodicMemorySummarizer` with any `LlmClientInterface`; falls back to truncating bullets on LLM errors.

**Circuit breaker** — `ToolCircuitBreaker` on `ToolExecutor` opens after repeated `tool_exception` / `timeout` failures and returns `error_type: circuit_open` until cooldown.

**Parallel tools** — tools implementing `ParallelInvokableToolInterface` with a static `invokeParallel()` can run in batches via `ext-parallel` when `parallelToolCalls` is enabled. High-risk tools always run sequentially; HITL unchanged.

Laravel:

```env
MLIDEA_MEMORY_SUMMARIZER=llm
MLIDEA_MEMORY_LLM_PROVIDER=echo
MLIDEA_CIRCUIT_BREAKER=true
MLIDEA_PARALLEL_TOOLS=true
MLIDEA_PARALLEL_AUTOLOAD=/path/to/vendor/autoload.php
```

Demos: `examples/agents/20_llm_memory_demo.php`, `21_circuit_breaker_demo.php`, `22_parallel_tools_demo.php`

**Diary + sticky notes together:** `examples/agents/23_agent_diary_and_memory_demo.php` — `AgentStateStore` (full session JSON) plus `FileAgentMemoryStore` (episodic summary when context is windowed).

---

## See also

- [`examples/ai-admin/INTERACTION.md`](../examples/ai-admin/INTERACTION.md) — interaction loops, diagrams
- [`docs/AGENT_EXECUTION_PLAN.md`](AGENT_EXECUTION_PLAN.md) — roadmap
- [`packages/laravel/README.md`](../packages/laravel/README.md) — install + config
