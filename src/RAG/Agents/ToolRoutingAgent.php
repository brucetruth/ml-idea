<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

use ML\IDEA\RAG\Contracts\AgentStateStoreInterface;
use ML\IDEA\RAG\Contracts\StreamingToolRoutingModelInterface;
use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Contracts\ToolRoutingModelInterface;
use ML\IDEA\RAG\Contracts\ToolSchemaInterface;
use ML\IDEA\RAG\Contracts\UsageAwareToolRoutingModelInterface;
use ML\IDEA\RAG\LLM\ToolRoutingDecisionParser;

final class ToolRoutingAgent
{
    /** @var array<string, ToolInterface> */
    private array $tools = [];

    /** @param array<int, ToolInterface> $tools */
    public function __construct(
        private readonly ToolRoutingModelInterface $model,
        array $tools,
        private readonly int $maxIterations = 8,
        private readonly string $agentName = 'ToolRoutingAgent',
        /** @var array<int, string> */
        private readonly array $agentFeatures = [],
        private readonly ?string $systemPrompt = null,
        private readonly ?ToolExecutor $toolExecutor = null,
        private readonly ?AgentPlanner $planner = null,
        private readonly ?AgentBudget $budget = null,
        private readonly ?AgentContextManager $contextManager = null,
        private readonly bool $includePlanningPrompt = true,
        private readonly ?AgentStateStoreInterface $stateStore = null,
        private readonly ?AgentHandoffRegistry $handoffRegistry = null,
    ) {
        foreach ($tools as $tool) {
            if (isset($this->tools[$tool->name()])) {
                throw new \InvalidArgumentException(sprintf('Duplicate tool name registered: %s', $tool->name()));
            }
            $this->tools[$tool->name()] = $tool;
        }
    }

    public function getSystemPrompt(): string
    {
        if (is_string($this->systemPrompt) && trim($this->systemPrompt) !== '') {
            return $this->withHandoffInstructions(trim($this->systemPrompt));
        }

        $name = trim($this->agentName);
        $features = array_values(array_filter(array_map(
            static fn (mixed $f): string => trim((string) $f),
            $this->agentFeatures
        ), static fn (string $f): bool => $f !== ''));

        if ($name === 'ToolRoutingAgent' && $features === []) {
            $base = 'You are a tool-using agent. Decide whether to call a tool or answer directly.';
            if ($this->includePlanningPrompt) {
                $base .= "\n\n" . ($this->planner ?? new AgentPlanner())->planningPrompt();
            }
            return $this->withHandoffInstructions($base);
        }

        $lines = [
            sprintf('You are %s, a tool-using agent.', $name !== '' ? $name : 'ToolRoutingAgent'),
            'Decide whether to call a tool or answer directly.',
        ];

        if ($features !== []) {
            $lines[] = 'Agent features:';
            foreach ($features as $feature) {
                $lines[] = '- ' . $feature;
            }
        }

        $prompt = implode("\n", $lines);
        if ($this->includePlanningPrompt) {
            $prompt .= "\n\n" . ($this->planner ?? new AgentPlanner())->planningPrompt();
        }

        return $this->withHandoffInstructions($prompt);
    }

    private function withHandoffInstructions(string $prompt): string
    {
        if ($this->handoffRegistry === null || $this->handoffRegistry->isEmpty()) {
            return $prompt;
        }

        $lines = [
            '',
            'Available specialist agents for handoff:',
        ];
        foreach ($this->handoffRegistry->descriptions() as $name => $description) {
            $lines[] = sprintf('- %s: %s', $name, $description);
        }
        $lines[] = 'To delegate work, return JSON: {"type":"handoff","agent":"specialist_name","content":"task description"}.';
        $lines[] = 'After a specialist responds, synthesize a final answer for the user.';

        return $prompt . implode("\n", $lines);
    }

    public function handoffRegistry(): ?AgentHandoffRegistry
    {
        return $this->handoffRegistry;
    }

    /**
     * @return array{answer: string, iterations: int, tool_calls: array<int, array{name: string, input: array<string, mixed>, output: string}>, trace: array<int, array{role: string, content: string}>, structured_tool_calls: array<int, array<string, mixed>>, stop_reason: string, decisions: array<int, array<string, mixed>>, events: array<int, array<string, mixed>>, usage: array<string, mixed>, state: array<string, mixed>, budget: array<string, mixed>, approval_token?: string, pending_approval?: array<string, mixed>}
     */
    public function chat(string $userMessage): array
    {
        return $this->chatWithState(new AgentState($userMessage, $this->getSystemPrompt()));
    }

    /**
     * @return array{answer: string, iterations: int, tool_calls: array<int, array{name: string, input: array<string, mixed>, output: string}>, trace: array<int, array{role: string, content: string}>, structured_tool_calls: array<int, array<string, mixed>>, stop_reason: string, decisions: array<int, array<string, mixed>>, events: array<int, array<string, mixed>>, usage: array<string, mixed>, state: array<string, mixed>, budget: array<string, mixed>, approval_token?: string, pending_approval?: array<string, mixed>}
     */
    public function chatWithState(AgentState $state): array
    {
        return $this->drainRunLoop($this->runLoop($state));
    }

    /**
     * @return array{answer: string, iterations: int, tool_calls: array<int, array{name: string, input: array<string, mixed>, output: string}>, trace: array<int, array{role: string, content: string}>, structured_tool_calls: array<int, array<string, mixed>>, stop_reason: string, decisions: array<int, array<string, mixed>>, events: array<int, array<string, mixed>>, usage: array<string, mixed>, state: array<string, mixed>, budget: array<string, mixed>, approval_token?: string, pending_approval?: array<string, mixed>, session_id?: string}
     */
    public function chatInSession(string $sessionId, string $userMessage): array
    {
        $store = $this->requireStateStore();
        $state = $store->load($sessionId);
        if ($state === null) {
            $result = $this->chat($userMessage);
        } else {
            $state->addMessage('user', $userMessage);
            $result = $this->chatWithState($state);
        }

        return $this->persistSessionResult($sessionId, $result);
    }

    /**
     * @return \Generator<int, AgentStreamEvent, mixed, array<string, mixed>>
     */
    public function chatStreamInSession(string $sessionId, string $userMessage): \Generator
    {
        $store = $this->requireStateStore();
        $state = $store->load($sessionId);
        if ($state === null) {
            $state = new AgentState($userMessage, $this->getSystemPrompt());
        } else {
            $state->addMessage('user', $userMessage);
        }

        $generator = $this->runLoop($state);
        while ($generator->valid()) {
            yield $generator->current();
            $generator->next();
        }

        $this->persistSessionResult($sessionId, $generator->getReturn());
    }

    /** @return \Generator<int, AgentStreamEvent, mixed, array<string, mixed>> */
    public function chatStream(string $userMessage): \Generator
    {
        return $this->runLoop(new AgentState($userMessage, $this->getSystemPrompt()));
    }

    /** @return \Generator<int, AgentStreamEvent, mixed, array<string, mixed>> */
    public function chatStreamWithState(AgentState $state): \Generator
    {
        return $this->runLoop($state);
    }

    /**
     * @return array{answer: string, iterations: int, tool_calls: array<int, array{name: string, input: array<string, mixed>, output: string}>, trace: array<int, array{role: string, content: string}>, structured_tool_calls: array<int, array<string, mixed>>, stop_reason: string, decisions: array<int, array<string, mixed>>, events: array<int, array<string, mixed>>, usage: array<string, mixed>, state: array<string, mixed>, budget: array<string, mixed>, approval_token?: string, pending_approval?: array<string, mixed>}
     */
    public function resumeWithApproval(AgentState $state, bool $approved, string $approvalToken): array
    {
        $pending = $state->pendingApproval();
        if ($pending === null) {
            throw new \InvalidArgumentException('No pending approval found in agent state.');
        }

        if (($pending['approval_token'] ?? '') !== $approvalToken) {
            throw new \InvalidArgumentException('Approval token does not match pending tool call.');
        }

        $state->clearPendingApproval();
        if (!$approved) {
            $state->addMessage('assistant', 'Tool execution denied by human reviewer.');
            $state->recordEvent(['type' => 'approval_denied', 'tool' => (string) ($pending['tool'] ?? '')]);
        } else {
            $this->executeApprovedToolCall(
                $state,
                (string) ($pending['tool'] ?? ''),
                is_array($pending['input'] ?? null) ? $pending['input'] : [],
                isset($pending['provider_call_id']) ? (string) $pending['provider_call_id'] : '',
                $this->toolExecutor ?? new ToolExecutor(),
                $this->planner ?? new AgentPlanner(),
            );
        }

        return $this->drainRunLoop($this->runLoop($state, resume: true));
    }

    /**
     * @return array{answer: string, iterations: int, tool_calls: array<int, array{name: string, input: array<string, mixed>, output: string}>, trace: array<int, array{role: string, content: string}>, structured_tool_calls: array<int, array<string, mixed>>, stop_reason: string, decisions: array<int, array<string, mixed>>, events: array<int, array<string, mixed>>, usage: array<string, mixed>, state: array<string, mixed>, budget: array<string, mixed>, approval_token?: string, pending_approval?: array<string, mixed>, session_id?: string}
     */
    public function resumeWithApprovalInSession(string $sessionId, bool $approved, string $approvalToken): array
    {
        $store = $this->requireStateStore();
        $state = $store->load($sessionId);
        if ($state === null) {
            throw new \InvalidArgumentException(sprintf('Session not found: %s', $sessionId));
        }

        $result = $this->resumeWithApproval($state, $approved, $approvalToken);

        return $this->persistSessionResult($sessionId, $result);
    }

    public function stateStore(): ?AgentStateStoreInterface
    {
        return $this->stateStore;
    }

    private function requireStateStore(): AgentStateStoreInterface
    {
        if ($this->stateStore === null) {
            throw new \InvalidArgumentException('AgentStateStore is required for session-based agent methods.');
        }

        return $this->stateStore;
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function persistSessionResult(string $sessionId, array $result): array
    {
        $this->requireStateStore()->save($sessionId, AgentState::fromArray(is_array($result['state'] ?? null) ? $result['state'] : []));
        $result['session_id'] = $sessionId;

        return $result;
    }

    /**
     * @param \Generator<int, AgentStreamEvent, mixed, array<string, mixed>> $generator
     * @return array<string, mixed>
     */
    private function drainRunLoop(\Generator $generator): array
    {
        while ($generator->valid()) {
            $generator->next();
        }

        return $generator->getReturn();
    }

    /**
     * @return \Generator<int, AgentStreamEvent, mixed, array<string, mixed>>
     */
    private function runLoop(AgentState $state, bool $resume = false): \Generator
    {
        $startedAt = $state->runStartedAt() > 0.0 ? $state->runStartedAt() : microtime(true);
        if (!$resume) {
            $state->setRunStartedAt($startedAt);
            $state->setRunIteration(0);
        }

        $budget = $this->budget ?? new AgentBudget($this->maxIterations, ($this->toolExecutor ?? new ToolExecutor())->policy()->maxToolCalls());
        $planner = $this->planner ?? new AgentPlanner();
        $executor = $this->toolExecutor ?? new ToolExecutor();
        $toolSchemas = $this->buildToolSchemas();
        $toolCallBudget = min($executor->policy()->maxToolCalls(), $budget->maxToolCalls);
        $startIteration = $state->runIteration();

        yield new AgentStreamEvent('run_start', ['goal' => $state->goal, 'resume' => $resume]);

        for ($i = $startIteration; $i < min($this->maxIterations, $budget->maxIterations); $i++) {
            $state->setRunIteration($i);
            yield new AgentStreamEvent('iteration_start', ['iteration' => $i + 1]);

            if ($budget->exceededRuntime($startedAt)) {
                $result = $state->response('Runtime budget reached without final answer.', $i, 'runtime_budget_exceeded', $this->budgetSnapshot($budget, $startedAt, $state));
                yield AgentStreamEvent::final($result);
                return $result;
            }

            try {
                $routingMessages = $this->contextManager === null
                    ? $state->messages()
                    : $this->contextManager->prepareForRouting($state->messages());

                if ($this->model instanceof StreamingToolRoutingModelInterface) {
                    $content = '';
                    foreach ($this->model->streamRespond($routingMessages, $toolSchemas) as $token) {
                        $content .= $token;
                        yield new AgentStreamEvent('token', ['iteration' => $i + 1, 'token' => $token]);
                    }
                    $decisionRaw = ToolRoutingDecisionParser::parse($content);
                } else {
                    $decisionRaw = $this->model->respond($routingMessages, $toolSchemas);
                }
            } catch (\Throwable $e) {
                $result = $state->response('Routing model error: ' . $e->getMessage(), $i + 1, 'model_exception', $this->budgetSnapshot($budget, $startedAt, $state));
                yield new AgentStreamEvent('error', ['message' => $e->getMessage()]);
                yield AgentStreamEvent::final($result);
                return $result;
            }

            $decision = AgentDecision::fromArray($decisionRaw);
            if ($this->model instanceof UsageAwareToolRoutingModelInterface) {
                $state->addUsage($this->model->lastUsage());
                $usageStop = $budget->exceededUsage($state->usage());
                if ($usageStop !== null) {
                    $result = $state->response('Usage budget reached without final answer.', $i + 1, $usageStop, $this->budgetSnapshot($budget, $startedAt, $state));
                    yield AgentStreamEvent::final($result);
                    return $result;
                }
            }

            $state->recordDecision($decision, $i + 1);
            yield new AgentStreamEvent('decision', [
                'iteration' => $i + 1,
                'type' => $decision->type,
                'content' => $decision->content,
                'confidence' => $decision->confidence,
                'tool_calls' => $decision->toolCalls,
                'handoff_target' => $decision->handoffTarget,
            ]);

            $terminal = $this->handleTerminalDecision($state, $decision, $i + 1, $budget, $startedAt);
            if ($terminal !== null) {
                yield AgentStreamEvent::final($terminal);
                return $terminal;
            }

            if ($decision->type === 'plan') {
                $planner->recordPlan($state, $decision->content);
                continue;
            }

            if ($decision->type === 'handoff') {
                yield new AgentStreamEvent('handoff_start', [
                    'iteration' => $i + 1,
                    'agent' => $decision->handoffTarget,
                    'task' => $decision->content,
                ]);
                $this->executeHandoff($state, $decision);
                yield new AgentStreamEvent('handoff_result', [
                    'iteration' => $i + 1,
                    'agent' => $decision->handoffTarget,
                ]);
                $planner->recordReflection($state, 'Observed specialist handoff result; synthesize final answer or delegate again.');
                yield new AgentStreamEvent('reflect', ['iteration' => $i + 1]);
                continue;
            }

            if ($decision->type !== 'tool_call' && $decision->type !== 'tool_calls') {
                $state->addMessage('assistant', 'Invalid decision type; provide final answer.');
                continue;
            }

            foreach ($decision->toolCalls as $requestedCall) {
                if (count($state->legacyToolCalls()) >= $toolCallBudget) {
                    $result = $state->response('Tool call budget reached without final answer.', $i + 1, 'tool_budget_exceeded', $this->budgetSnapshot($budget, $startedAt, $state));
                    yield AgentStreamEvent::final($result);
                    return $result;
                }

                $toolName = $requestedCall['tool'];
                $toolInput = $requestedCall['input'];
                $providerCallId = isset($requestedCall['provider_call_id']) ? (string) $requestedCall['provider_call_id'] : '';
                $riskLevel = isset($this->tools[$toolName]) && $this->tools[$toolName] instanceof ToolSchemaInterface
                    ? $this->tools[$toolName]->riskLevel()
                    : 'medium';

                if (isset($this->tools[$toolName]) && $executor->policy()->shouldPauseForApproval($toolName, $riskLevel, $toolInput)) {
                    $approvalToken = $executor->policy()->approvalToken($toolName, $toolInput);
                    $state->setPendingApproval([
                        'tool' => $toolName,
                        'input' => $toolInput,
                        'provider_call_id' => $providerCallId,
                        'risk_level' => $riskLevel,
                        'approval_token' => $approvalToken,
                        'iteration' => $i + 1,
                    ]);
                    $state->setRunIteration($i + 1);
                    $answer = sprintf('Approval required before executing high-risk tool: %s', $toolName);
                    $result = $state->response($answer, $i + 1, 'awaiting_approval', $this->budgetSnapshot($budget, $startedAt, $state), $approvalToken);
                    yield new AgentStreamEvent('approval_required', [
                        'tool' => $toolName,
                        'risk_level' => $riskLevel,
                        'approval_token' => $approvalToken,
                    ]);
                    yield AgentStreamEvent::final($result);
                    return $result;
                }

                yield new AgentStreamEvent('tool_start', ['tool' => $toolName, 'input' => $toolInput]);
                $structured = $this->executeToolCall($state, $toolName, $toolInput, $providerCallId, $executor);
                yield new AgentStreamEvent('tool_result', $structured);
                $planner->observeToolResult($state, $toolName, (bool) ($structured['ok'] ?? false));
                $this->appendToolMessages($state, $toolName, $toolInput, $structured, $providerCallId);
            }

            $planner->recordReflection($state, 'Observed tool results; decide whether to continue or final answer.');
            yield new AgentStreamEvent('reflect', ['iteration' => $i + 1]);
        }

        $result = $state->response('Max iterations reached without final answer.', min($this->maxIterations, $budget->maxIterations), 'max_iterations', $this->budgetSnapshot($budget, $startedAt, $state));
        yield AgentStreamEvent::final($result);
        return $result;
    }

    /** @return array<int, array<string, mixed>> */
    private function buildToolSchemas(): array
    {
        $toolSchemas = [];
        foreach ($this->tools as $tool) {
            $schema = ['name' => $tool->name(), 'description' => $tool->description()];
            if ($tool instanceof ToolSchemaInterface) {
                $schema['input_schema'] = $tool->inputSchema();
                $schema['examples'] = $tool->examples();
                $schema['risk_level'] = $tool->riskLevel();
            }
            $toolSchemas[] = $schema;
        }

        return $toolSchemas;
    }

    private function executeHandoff(AgentState $state, AgentDecision $decision): void
    {
        $target = $decision->handoffTarget ?? '';
        $task = trim($decision->content) !== '' ? trim($decision->content) : $state->goal;

        if ($target === '') {
            $state->addMessage('assistant', 'Handoff requested without a specialist agent name.');
            return;
        }

        if ($this->handoffRegistry === null) {
            $state->addMessage('assistant', sprintf('Handoff to %s requested but no specialists are registered.', $target));
            return;
        }

        $specialist = $this->handoffRegistry->get($target);
        if ($specialist === null) {
            $state->addMessage('assistant', sprintf('Unknown handoff target: %s', $target));
            return;
        }

        $subResult = $specialist->chat($task);
        $state->recordHandoff($target, $task, $subResult);
        $state->addMessage('assistant', sprintf('HANDOFF %s: %s', $target, (string) ($subResult['answer'] ?? '')));
    }

    /**
     * @param array<string, mixed> $budget
     * @return array<string, mixed>|null
     */
    private function handleTerminalDecision(AgentState $state, AgentDecision $decision, int $iteration, AgentBudget $budget, float $startedAt): ?array
    {
        return match ($decision->type) {
            'final' => $state->response($decision->content !== '' ? $decision->content : 'No response content.', $iteration, 'final', $this->budgetSnapshot($budget, $startedAt, $state)),
            'clarify' => $state->response($decision->content !== '' ? $decision->content : 'Please clarify the request.', $iteration, 'clarify', $this->budgetSnapshot($budget, $startedAt, $state)),
            'refuse' => $state->response($decision->content !== '' ? $decision->content : 'I cannot safely complete that request.', $iteration, 'refuse', $this->budgetSnapshot($budget, $startedAt, $state)),
            default => null,
        };
    }

    /** @param array<string, mixed> $toolInput */
    private function executeApprovedToolCall(
        AgentState $state,
        string $toolName,
        array $toolInput,
        string $providerCallId,
        ToolExecutor $executor,
        AgentPlanner $planner,
    ): void {
        if (!isset($this->tools[$toolName])) {
            return;
        }

        $structured = $this->executeToolCall($state, $toolName, $toolInput, $providerCallId, $executor, approvalGranted: true);
        $planner->observeToolResult($state, $toolName, (bool) ($structured['ok'] ?? false));
        $this->appendToolMessages($state, $toolName, $toolInput, $structured, $providerCallId);
        $planner->recordReflection($state, 'Observed approved tool result; decide whether to continue or final answer.');
    }

    /**
     * @param array<string, mixed> $toolInput
     * @return array<string, mixed>
     */
    private function executeToolCall(
        AgentState $state,
        string $toolName,
        array $toolInput,
        string $providerCallId,
        ToolExecutor $executor,
        bool $approvalGranted = false,
    ): array {
        if (!isset($this->tools[$toolName])) {
            $output = sprintf('Tool not found: %s', $toolName);
            $structured = [
                'ok' => false,
                'tool' => $toolName,
                'input' => $toolInput,
                'output' => $output,
                'data' => null,
                'duration_ms' => 0,
                'error' => $output,
                'error_type' => 'unknown_tool',
                'truncated' => false,
            ];
        } else {
            $result = $executor->execute($this->tools[$toolName], $toolInput, $approvalGranted);
            $output = $result->output;
            $structured = $result->toArray();
        }

        if ($providerCallId !== '') {
            $structured['provider_call_id'] = $providerCallId;
        }

        $state->recordToolCall(['name' => $toolName, 'input' => $toolInput, 'output' => $output], $structured);

        return $structured;
    }

    /** @param array<string, mixed> $toolInput @param array<string, mixed> $structured */
    private function appendToolMessages(
        AgentState $state,
        string $toolName,
        array $toolInput,
        array $structured,
        string $providerCallId,
    ): void {
        $output = (string) ($structured['output'] ?? '');
        if ($providerCallId !== '') {
            $state->addProviderToolCallMessage($providerCallId, $toolName, $toolInput);
            $state->addProviderToolResultMessage($providerCallId, $output);
            return;
        }

        $state->addMessage('assistant', sprintf('TOOL_CALL %s %s', $toolName, json_encode($toolInput, JSON_THROW_ON_ERROR)));
        $state->addMessage('tool', json_encode($structured, JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    private function budgetSnapshot(AgentBudget $budget, float $startedAt, AgentState $state): array
    {
        return [
            'max_iterations' => $budget->maxIterations,
            'max_tool_calls' => $budget->maxToolCalls,
            'max_runtime_ms' => $budget->maxRuntimeMs,
            'elapsed_ms' => $budget->elapsedMs($startedAt),
            'tool_calls_used' => count($state->legacyToolCalls()),
            'iterations_recorded' => count($state->decisions()),
            'usage' => $state->usage()->toArray(),
        ];
    }
}
