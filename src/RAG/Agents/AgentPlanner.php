<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

final class AgentPlanner
{
    public function planningPrompt(): string
    {
        return 'Use a plan-act-observe-reflect-final loop. Plan briefly, call tools only when needed, observe results, reflect on whether enough information is available, then final answer.';
    }

    public function recordPlan(AgentState $state, string $plan): void
    {
        $state->addMessage('assistant', 'PLAN ' . $plan);
        $state->recordEvent(['type' => 'plan', 'content' => $plan]);
    }

    public function recordReflection(AgentState $state, string $reflection): void
    {
        $state->addMessage('assistant', 'REFLECT ' . $reflection);
        $state->recordEvent(['type' => 'reflect', 'content' => $reflection]);
    }

    public function observeToolResult(AgentState $state, string $toolName, bool $ok): void
    {
        $state->recordEvent(['type' => 'observe', 'tool' => $toolName, 'ok' => $ok]);
    }
}

