<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

final class AgentState
{
    /** @var array<int, array{role: string, content: string}> */
    private array $messages;

    /** @var array<int, array{name: string, input: array<string, mixed>, output: string}> */
    private array $legacyToolCalls = [];

    /** @var array<int, array<string, mixed>> */
    private array $structuredToolCalls = [];

    /** @var array<int, array<string, mixed>> */
    private array $decisions = [];

    /** @var array<int, array<string, mixed>> */
    private array $events = [];

    private AgentUsage $usage;

    /** @var array<string, mixed>|null */
    private ?array $pendingApproval = null;

    private int $runIteration = 0;

    private float $runStartedAt = 0.0;

    /** @var array<int, array<string, mixed>> */
    private array $handoffs = [];

    public function __construct(public readonly string $goal, string $systemPrompt)
    {
        $this->usage = new AgentUsage();
        $this->messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $goal],
        ];
        $this->events[] = ['type' => 'goal', 'content' => $goal];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $goal = isset($payload['goal']) ? (string) $payload['goal'] : '';
        $systemPrompt = isset($payload['system_prompt']) ? (string) $payload['system_prompt'] : '';
        $state = new self($goal, $systemPrompt);

        if (isset($payload['messages']) && is_array($payload['messages'])) {
            /** @var array<int, array{role: string, content: string}> $messages */
            $messages = $payload['messages'];
            $state->messages = $messages;
        }
        if (isset($payload['tool_calls']) && is_array($payload['tool_calls'])) {
            /** @var array<int, array{name: string, input: array<string, mixed>, output: string}> $toolCalls */
            $toolCalls = $payload['tool_calls'];
            $state->legacyToolCalls = $toolCalls;
        }
        if (isset($payload['structured_tool_calls']) && is_array($payload['structured_tool_calls'])) {
            /** @var array<int, array<string, mixed>> $structured */
            $structured = $payload['structured_tool_calls'];
            $state->structuredToolCalls = $structured;
        }
        if (isset($payload['decisions']) && is_array($payload['decisions'])) {
            /** @var array<int, array<string, mixed>> $decisions */
            $decisions = $payload['decisions'];
            $state->decisions = $decisions;
        }
        if (isset($payload['events']) && is_array($payload['events'])) {
            /** @var array<int, array<string, mixed>> $events */
            $events = $payload['events'];
            $state->events = $events;
        }
        if (isset($payload['usage']) && is_array($payload['usage'])) {
            $state->usage = new AgentUsage(
                isset($payload['usage']['prompt_tokens']) ? (int) $payload['usage']['prompt_tokens'] : 0,
                isset($payload['usage']['completion_tokens']) ? (int) $payload['usage']['completion_tokens'] : 0,
                isset($payload['usage']['total_tokens']) ? (int) $payload['usage']['total_tokens'] : 0,
                isset($payload['usage']['estimated_cost']) ? (float) $payload['usage']['estimated_cost'] : 0.0,
            );
        }
        if (isset($payload['pending_approval']) && is_array($payload['pending_approval'])) {
            $state->pendingApproval = $payload['pending_approval'];
        }
        if (isset($payload['run_iteration'])) {
            $state->runIteration = (int) $payload['run_iteration'];
        }
        if (isset($payload['run_started_at'])) {
            $state->runStartedAt = (float) $payload['run_started_at'];
        }
        if (isset($payload['handoffs']) && is_array($payload['handoffs'])) {
            $state->handoffs = $payload['handoffs'];
        }

        return $state;
    }

    /** @return array<int, array{role: string, content: string}> */
    public function messages(): array
    {
        return $this->messages;
    }

    public function addMessage(string $role, string $content): void
    {
        $this->messages[] = ['role' => $role, 'content' => $content];
    }

    /** @param array<string, mixed> $input */
    public function addProviderToolCallMessage(string $toolCallId, string $toolName, array $input): void
    {
        $this->messages[] = [
            'role' => 'assistant',
            'content' => '',
            'tool_calls' => [[
                'id' => $toolCallId,
                'type' => 'function',
                'function' => [
                    'name' => $toolName,
                    'arguments' => json_encode($input, JSON_THROW_ON_ERROR),
                ],
            ]],
        ];
    }

    public function addProviderToolResultMessage(string $toolCallId, string $output): void
    {
        $this->messages[] = [
            'role' => 'tool',
            'content' => $output,
            'tool_call_id' => $toolCallId,
        ];
    }

    public function recordDecision(AgentDecision $decision, int $iteration): void
    {
        $this->decisions[] = [
            'iteration' => $iteration,
            'type' => $decision->type,
            'content' => $decision->content,
            'tool_calls' => $decision->toolCalls,
            'confidence' => $decision->confidence,
            'handoff_target' => $decision->handoffTarget,
        ];
    }

    /** @param array<string, mixed> $event */
    public function recordEvent(array $event): void
    {
        $this->events[] = $event;
    }

    public function addUsage(AgentUsage $usage): void
    {
        $this->usage = $this->usage->plus($usage);
        $this->events[] = ['type' => 'usage', ...$usage->toArray()];
    }

    /**
     * @param array{name: string, input: array<string, mixed>, output: string} $legacy
     * @param array<string, mixed> $structured
     */
    public function recordToolCall(array $legacy, array $structured): void
    {
        $this->legacyToolCalls[] = $legacy;
        $this->structuredToolCalls[] = $structured;
        $this->events[] = ['type' => 'tool_call', 'tool' => $legacy['name'], 'ok' => $structured['ok'] ?? null];
    }

    /** @return array<int, array{name: string, input: array<string, mixed>, output: string}> */
    public function legacyToolCalls(): array
    {
        return $this->legacyToolCalls;
    }

    /** @return array<int, array<string, mixed>> */
    public function structuredToolCalls(): array
    {
        return $this->structuredToolCalls;
    }

    /** @return array<int, array<string, mixed>> */
    public function decisions(): array
    {
        return $this->decisions;
    }

    /** @return array<int, array<string, mixed>> */
    public function events(): array
    {
        return $this->events;
    }

    public function usage(): AgentUsage
    {
        return $this->usage;
    }

    /** @return array<string, mixed>|null */
    public function pendingApproval(): ?array
    {
        return $this->pendingApproval;
    }

    /** @param array<string, mixed> $pending */
    public function setPendingApproval(array $pending): void
    {
        $this->pendingApproval = $pending;
        $this->events[] = ['type' => 'approval_required', ...$pending];
    }

    public function clearPendingApproval(): void
    {
        $this->pendingApproval = null;
    }

    /** @return array<int, array<string, mixed>> */
    public function handoffs(): array
    {
        return $this->handoffs;
    }

    /** @param array<string, mixed> $result */
    public function recordHandoff(string $agent, string $task, array $result): void
    {
        $entry = [
            'agent' => $agent,
            'task' => $task,
            'answer' => (string) ($result['answer'] ?? ''),
            'stop_reason' => (string) ($result['stop_reason'] ?? ''),
            'tool_call_count' => count(is_array($result['tool_calls'] ?? null) ? $result['tool_calls'] : []),
        ];
        $this->handoffs[] = $entry;
        $this->events[] = ['type' => 'handoff', ...$entry];

        $usage = is_array($result['usage'] ?? null) ? $result['usage'] : [];
        $this->addUsage(new AgentUsage(
            isset($usage['prompt_tokens']) ? (int) $usage['prompt_tokens'] : 0,
            isset($usage['completion_tokens']) ? (int) $usage['completion_tokens'] : 0,
            isset($usage['total_tokens']) ? (int) $usage['total_tokens'] : 0,
            isset($usage['estimated_cost']) ? (float) $usage['estimated_cost'] : 0.0,
        ));
    }

    public function runIteration(): int
    {
        return $this->runIteration;
    }

    public function setRunIteration(int $iteration): void
    {
        $this->runIteration = $iteration;
    }

    public function runStartedAt(): float
    {
        return $this->runStartedAt;
    }

    public function setRunStartedAt(float $startedAt): void
    {
        $this->runStartedAt = $startedAt;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'goal' => $this->goal,
            'system_prompt' => $this->messages[0]['content'] ?? '',
            'messages' => $this->messages,
            'tool_calls' => $this->legacyToolCalls,
            'structured_tool_calls' => $this->structuredToolCalls,
            'decisions' => $this->decisions,
            'events' => $this->events,
            'usage' => $this->usage->toArray(),
            'pending_approval' => $this->pendingApproval,
            'run_iteration' => $this->runIteration,
            'run_started_at' => $this->runStartedAt,
            'handoffs' => $this->handoffs,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public static function fromJson(string $json): self
    {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        return self::fromArray(is_array($decoded) ? $decoded : []);
    }

    /**
     * @param array<string, mixed> $budget
     * @return array{answer: string, iterations: int, tool_calls: array<int, array{name: string, input: array<string, mixed>, output: string}>, trace: array<int, array{role: string, content: string}>, structured_tool_calls: array<int, array<string, mixed>>, stop_reason: string, decisions: array<int, array<string, mixed>>, events: array<int, array<string, mixed>>, usage: array<string, mixed>, state: array<string, mixed>, budget: array<string, mixed>}
     */
    public function response(string $answer, int $iterations, string $stopReason, array $budget = [], ?string $approvalToken = null): array
    {
        $payload = [
            'answer' => $answer,
            'iterations' => $iterations,
            'tool_calls' => $this->legacyToolCalls,
            'trace' => $this->messages,
            'structured_tool_calls' => $this->structuredToolCalls,
            'stop_reason' => $stopReason,
            'decisions' => $this->decisions,
            'events' => $this->events,
            'usage' => $this->usage->toArray(),
            'handoffs' => $this->handoffs,
            'state' => $this->toArray(),
            'budget' => $budget,
        ];

        if ($approvalToken !== null) {
            $payload['approval_token'] = $approvalToken;
        }

        if ($this->pendingApproval !== null) {
            $payload['pending_approval'] = $this->pendingApproval;
        }

        return $payload;
    }
}
