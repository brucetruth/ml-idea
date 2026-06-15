# AI Admin Example

This folder demonstrates how to build an **AI admin assistant** with custom tools — one tool per admin action (list users, ban user, refund order, etc.).

The pattern maps cleanly to Laravel: each tool is a class resolved from the container, registered in `config/mlidea.php` or via `MlIdeaAgent::registerTool()`.

## What is included

| File | Purpose |
|---|---|
| `Support/AdminStore.php` | In-memory users/orders backend (swap for Eloquent in production) |
| `Support/AdminHeuristicRouter.php` | Offline router for demos without an LLM API key |
| `Support/AdminToolset.php` | Bundles all admin tools |
| `Tools/*.php` | Custom tools with JSON Schema + risk levels |
| `run_admin_agent.php` | Standalone agent demo |
| `run_autonomous_admin.php` | Full AI admin — low-risk writes, no HITL |
| `run_admin_agent_hitl.php` | High-risk tools pause for human approval |

**Laravel integration:** [`packages/laravel/examples/ai-admin/`](../../packages/laravel/examples/ai-admin/)

## Tools

| Tool | Risk | Admin action |
|---|---|---|
| `list_users` | low | Browse users (optional role/status filter) |
| `get_user` | low | Inspect one user |
| `update_user_role` | medium | Promote/demote roles |
| `list_orders` | low | Browse orders |
| `add_user_note` | low | Write internal note (auto) |
| `tag_order` | low | Tag order internally (auto) |
| `update_support_ticket_status` | medium | Update ticket status (auto) |
| `ban_user` | **high** | Ban account (HITL-friendly) |
| `refund_order` | **high** | Issue refund (HITL-friendly) |

Implement `ToolInterface` + `ToolSchemaInterface` so the agent gets validation, examples, and risk-aware policy.

## Run locally (no Laravel)

```bash
php examples/ai-admin/run_admin_agent.php "List all users"
php examples/ai-admin/run_admin_agent.php "Show orders for user #2"
php examples/ai-admin/run_admin_agent.php "Set user #3 role editor"

php examples/ai-admin/run_autonomous_admin.php

php examples/ai-admin/run_agent_eval.php

php examples/ai-admin/run_admin_agent_hitl.php "Ban user #2 for chargeback abuse"
php examples/ai-admin/run_admin_agent_hitl.php "Refund order #101 for support ticket" yes
```

With a provider-backed model in Laravel (`MLIDEA_MODEL_DRIVER=openai`), the same tools work without `AdminHeuristicRouter` — the LLM chooses tools from schemas.

## How you interact with the agent

You send **natural language** (a user story). The agent decides which tools to call — you never invoke tools directly.

Read **[INTERACTION.md](INTERACTION.md)** for the full loop (CLI, multi-turn sessions, Laravel HTTP API, HITL approval).

**Multi-turn user story demo:**

```bash
php examples/ai-admin/run_admin_user_story.php
# Optional: real LLM routing
MLIDEA_USE_OPENAI=1 OPENAI_API_KEY=sk-... php examples/ai-admin/run_admin_user_story.php
```

**Customer refund request → agent triage:**

```bash
php examples/ai-admin/run_refund_request_workflow.php
php examples/ai-admin/run_refund_request_workflow.php deny-demo   # agent denies (shipped order)
```

Read **[INTERACTION.md](INTERACTION.md)** § Customer-initiated refund requests for the full flow diagram.

## Laravel integration

See [`packages/laravel/examples/ai-admin/README.md`](../../packages/laravel/examples/ai-admin/README.md) for:

- Copy-paste `App\AiAdmin` tools, store, controller, routes
- Service provider registration
- `config/mlidea.php` snippet

## Building your own admin tools

1. Create a class implementing `ToolInterface` (and `ToolSchemaInterface` for production).
2. Inject app services in the constructor (`UserRepository`, `BillingService`, …).
3. Return structured JSON from `invoke()` so the agent can summarize results.
4. Set `riskLevel()` to `high` for destructive actions and enable `MLIDEA_PAUSE_FOR_APPROVAL=true`.
5. Register the class in Laravel config or `MlIdeaAgent::registerTool()`.

See also `examples/agents/07_custom_rag_tool_demo.php` for a minimal custom tool walkthrough.
