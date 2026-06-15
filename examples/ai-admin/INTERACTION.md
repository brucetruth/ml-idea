# How You Interact With the AI Admin Agent

The agent is not a fixed script. You send **natural language** (a user story, question, or command). The **routing model** reads your message, the **system prompt**, and **tool schemas**, then decides each step: call a tool, ask for clarification, or give a final answer.

## The interaction loop

```
┌─────────────┐     user message ("Bob was double-charged…")      ┌──────────────────┐
│ Admin UI /  │ ─────────────────────────────────────────────────►│ ToolRoutingAgent │
│ CLI / API   │                                                   │                  │
└─────────────┘                                                   │  1. Build context│
       ▲                                                          │     (system +    │
       │                                                          │      history +   │
       │     answer + optional approval_token                      │      tool list)  │
       └──────────────────────────────────────────────────────────│                  │
                                                                  │  2. Model decides│
                                                                  │     tool_call or │
                                                                  │     final        │
                                                                  │                  │
                                                                  │  3. Execute tool │
                                                                  │  4. Reflect,     │
                                                                  │     repeat until │
                                                                  │     done         │
                                                                  └──────────────────┘
```

Each registered tool exposes:

- `name()` / `description()` — what the model sees
- `inputSchema()` — JSON Schema for parameters
- `riskLevel()` — policy / human approval

The model (OpenAI, Anthropic, Ollama, or a demo router) picks **which tool** and **which inputs** match your story.

## What you send (the “user story”)

You do **not** call tools yourself. You describe the situation in plain language:

| Example message | Typical agent behavior (with LLM) |
|---|---|
| "List all active customers" | `list_users` with `status: active` |
| "Who is user #2?" | `get_user` |
| "Bob says order #101 was a duplicate charge — check his orders" | `get_user` → `list_orders` → summarize |
| "Refund order #101, support ticket #8842" | `refund_order` (may pause for approval) |
| "Ban user #3 for repeated fraud" | `ban_user` (HITL when enabled) |

Multi-turn: keep the same `session_id` so the agent remembers prior tool results and your follow-ups ("yes, go ahead and refund").

## Standalone PHP

**Single message:**

```bash
php examples/ai-admin/run_admin_agent.php "Show orders for user #2"
```

**Multi-turn user story** (session persisted to disk):

```bash
php examples/ai-admin/run_admin_user_story.php
```

That script simulates an admin investigating a billing complaint across several messages.

**Offline note:** `run_admin_agent.php` uses `AdminHeuristicRouter` (keyword matching) so complex stories are limited. For real decision-making, use a provider model — same tools, same `chat()` API:

```php
use ML\IDEA\RAG\LLM\OpenAIToolRoutingModel;

$agent = new ToolRoutingAgent(
    new OpenAIToolRoutingModel(getenv('OPENAI_API_KEY')),
    AdminToolset::make(),
    systemPrompt: 'You are an AI admin assistant…',
);
$result = $agent->chat('Customer Bob (user #2) reports duplicate charge on order #101. Investigate and recommend next steps.');
```

## Laravel (real-world API)

After wiring the controller and routes:

```http
POST /api/admin/ai/chat
Content-Type: application/json

{
  "session_id": "admin-ada-2024-06-13",
  "message": "Customer Bob (user #2) emailed that order #101 was charged twice. Pull his profile and orders, then tell me if a refund looks correct."
}
```

Response:

```json
{
  "answer": "User #2 (Bob Buyer) has 2 orders. Order #101 is paid ($49.99). A refund may be appropriate if…",
  "stop_reason": "final",
  "session_id": "admin-ada-2024-06-13"
}
```

Follow-up in the **same session**:

```http
POST /api/admin/ai/chat

{
  "session_id": "admin-ada-2024-06-13",
  "message": "Yes, refund order #101. Reason: duplicate charge per ticket #8842."
}
```

If `MLIDEA_PAUSE_FOR_APPROVAL=true` and the model calls `refund_order`:

```json
{
  "stop_reason": "awaiting_approval",
  "approval_token": "a1b2c3…",
  "pending_approval": { "tool": "refund_order", "input": { "order_id": 101, "reason": "…" } },
  "state": { … }
}
```

Admin confirms in your UI → `POST /api/admin/ai/approve` with `state`, `approval_token`, `approved: true`.

## Autonomous AI admin (no human step)

For **low/medium-risk writes** the agent can update your backend directly inside the tool — no approval route, no `AgentApprovalContext`.

| Risk | Tools | HITL when `pause_for_approval=true`? |
|---|---|---|
| low | `list_*`, `get_*`, `add_user_note`, `tag_order` | No — runs immediately |
| medium | `update_user_role`, `update_support_ticket_status` | No — runs immediately |
| high | `ban_user`, `refund_order` | Yes — pauses until admin approves |

**Policy rule:** `pauseForApproval` only applies to tools whose `riskLevel()` is in `confirmationRequiredRiskLevels` (default: `['high']`). Low-risk tools still **mutate data** — they just don't move money or ban accounts.

```
Support ticket created     Job / webhook              Agent                    DB (via tools)
        │                       │                        │                            │
        │ POST /tickets         │                        │                            │
        ├──────────────────────►│ queue job              │                            │
        │                       ├───────────────────────►│ get_user, list_orders      │
        │                       │                        │ add_user_note ────────────►│
        │                       │                        │ tag_order ────────────────►│
        │                       │                        │ update_support_ticket… ───►│
        │                       │◄───────────────────────┤ final summary              │
        │◄──────────────────────┤ notify / inbox         │                            │
```

**Standalone demo** (writes applied to in-memory store, no pause):

```bash
php examples/ai-admin/run_autonomous_admin.php
```

**Laravel:** `ProcessAutonomousSupportTicketJob.example.php` + `config.mlidea.autonomous.snippet.php` (tool allow-list without `ban_user` / `refund_order`).

Each write tool injects your repository/Eloquent model in `invoke()` — same pattern as `RefundOrderTool`, but the job never calls `approvalContextFromResult()` because nothing pauses.

## Customer-initiated refund requests

This is the pattern you described: **the customer submits a form**, your app **queues the request**, then the **agent triages** it (investigate → approve / deny / escalate).

```
Customer form          Your app                    Agent                    Admin (optional)
     │                    │                          │                            │
     │ POST /refunds      │                          │                            │
     ├───────────────────►│ save RefundRequest       │                            │
     │                    │ status=pending           │                            │
     │                    │                          │                            │
     │                    │ Job: dispatch to agent   │                            │
     │                    ├─────────────────────────►│ get_user, list_orders      │
     │                    │  (structured prompt)     │ refund_order OR final deny │
     │                    │                          │                            │
     │                    │◄─────────────────────────┤ awaiting_approval?         │
     │                    │                          ├───────────────────────────►│ approve/deny
     │                    │ notify customer          │                            │
     │◄───────────────────┤                          │                            │
```

The customer does **not** chat with the LLM. Your backend builds a prompt from the ticket:

```php
$request = RefundRequest::fromCustomerForm($validated);
$store->submitRefundRequest($request);

$result = $agent->chat($request->toAgentMessage());
// or chatInSession('refund-request-' . $request->id, ...)
```

**Outcomes:**

| Agent behavior | Meaning |
|---|---|
| Calls `refund_order` → `awaiting_approval` | Agent recommends proceed; admin confirms (HITL) |
| Final answer contains "DENY" | Agent rejected; update ticket, notify customer |
| Final answer contains "ESCALATE" | Route to human support queue |
| `refund_order` completes | Approved and processed |

**Demo:**

```bash
php examples/ai-admin/run_refund_request_workflow.php              # approve path (order #101 paid)
php examples/ai-admin/run_refund_request_workflow.php deny-demo    # deny path (order #102 shipped)
```

**Laravel:** see `packages/laravel/examples/ai-admin/RefundRequestController.example.php` and `ProcessRefundRequestJob.example.php`.

When the agent pauses for approval, save **`AgentApprovalContext`** (`agent_review_context` JSON). Admin UI uses `GET .../review` for summary + investigation; `POST .../decide` with `{ "approved": true }` only — no raw `agent_state` in the HTTP body.

**Production tips:**

- Use a **provider model** (OpenAI/Anthropic) for nuanced policy decisions
- Keep `MLIDEA_PAUSE_FOR_APPROVAL=true` for `refund_order` even when the agent initiates it
- Store agent trace (`decisions`, `tool_calls`) on the `RefundRequest` row for audit
- Optionally add a read-only `get_refund_policy` tool so the agent cites rules

## What shapes good decisions

1. **System prompt** — role, safety rules ("inspect before mutating")
2. **Tool descriptions + schemas** — clear names like `refund_order`, good `examples()`
3. **Session history** — prior turns and tool outputs stay in context
4. **Risk levels + HITL** — destructive steps pause for a human
5. **Provider model** — GPT/Claude/Ollama native tool calling for nuanced stories

## See also

- `run_admin_user_story.php` — scripted multi-turn demo with verbose trace
- `run_autonomous_admin.php` — full AI admin, low-risk DB writes, no HITL
- `packages/laravel/examples/ai-admin/` — HTTP controller + routes
