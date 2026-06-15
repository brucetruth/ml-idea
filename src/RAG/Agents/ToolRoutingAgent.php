<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

use ML\IDEA\RAG\Contracts\AgentMemoryStoreInterface;
use ML\IDEA\RAG\Contracts\AgentRunLoggerInterface;
use ML\IDEA\RAG\Contracts\AgentStateStoreInterface;
use ML\IDEA\RAG\Contracts\AgentSpanScopeInterface;
use ML\IDEA\RAG\Contracts\AgentTracerInterface;
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

    private ?AgentRunLogContext $runLogContext = null;

    private ?string $memorySessionId = null;

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
        private readonly ?AgentTracerInterface $agentTracer = null,
        private readonly ?TraceRedactor $traceRedactor = null,
        private readonly ?AgentRunLoggerInterface $agentRunLogger = null,
        private readonly ?AgentMemoryStoreInterface $memoryStore = null,
        private readonly bool $orderToolCallsByRisk = true,
        private readonly bool $parallelToolCalls = false,
        private readonly ?string $parallelAutoloadPath = null,
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

    public function agentTracer(): AgentTracerInterface
    {
        return $this->agentTracer ?? new NoOpAgentTracer();
    }

    public function agentRunLogger(): AgentRunLoggerInterface
    {
        return $this->agentRunLogger ?? new NoOpAgentRunLogger();
    }

    /**
     * @return array{answer: string, iterations: int, tool_calls: array<int, array{name: string, input: array<string, mixed>, output: string}>, trace: array<int, array{role: string, content: string}>, structured_tool_calls: array<int, array<string, mixed>>, stop_reason: string, decisions: array<int, array<string, mixed>>, events: array<int, array<string, mixed>>, usage: array<string, mixed>, state: array<string, mixed>, budget: array<string, mixed>, approval_token?: string, pending_approval?: array<string, mixed>}
     */
    public function chat(string $userMessage): array
    {
        $ownedContext = false;
        if ($this->runLogContext === null) {
            $this->runLogContext = new AgentRunLogContext(userMessage: $userMessage);
            $ownedContext = true;
        }

        try {
            return $this->chatWithState(new AgentState($userMessage, $this->getSystemPrompt()));
        } finally {
            if ($ownedContext) {
                $this->runLogContext = null;
            }
        }
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
        $this->runLogContext = new AgentRunLogContext(sessionId: $sessionId, userMessage: $userMessage);
        $this->memorySessionId = $sessionId;

        try {
            $store = $this->requireStateStore();
            $state = $store->load($sessionId);
            if ($state === null) {
                $result = $this->chat($userMessage);
            } else {
                $state->addMessage('user', $userMessage);
                $result = $this->chatWithState($state);
            }

            return $this->persistSessionResult($sessionId, $result);
        } finally {
            $this->runLogContext = null;
            $this->memorySessionId = null;
        }
    }

    /**
     * @return \Generator<int, AgentStreamEvent, mixed, array<string, mixed>>
     */
    public function chatStreamInSession(string $sessionId, string $userMessage): \Generator
    {
        $this->runLogContext = new AgentRunLogContext(sessionId: $sessionId, userMessage: $userMessage);
        $this->memorySessionId = $sessionId;

        try {
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

            return $this->persistSessionResult($sessionId, $generator->getReturn());
        } finally {
            $this->runLogContext = null;
            $this->memorySessionId = null;
        }
    }

    /** @return \Generator<int, AgentStreamEvent, mixed, array<string, mixed>> */
    public function chatStream(string $userMessage): \Generator
    {
        $ownedContext = false;
        if ($this->runLogContext === null) {
            $this->runLogContext = new AgentRunLogContext(userMessage: $userMessage);
            $ownedContext = true;
        }

        try {
            yield from $this->runLoop(new AgentState($userMessage, $this->getSystemPrompt()));
        } finally {
            if ($ownedContext) {
                $this->runLogContext = null;
            }
        }
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
        $this->runLogContext = new AgentRunLogContext(resume: true);

        try {
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
        } finally {
            $this->runLogContext = null;
        }
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

        $runSpan = $this->agentTracer()->startSpan('agent.run', $this->traceAttributes([
            'agent.name' => $this->agentName,
            'agent.goal' => $this->truncateGoal($state->goal),
            'agent.resume' => $resume,
        ]));

        yield new AgentStreamEvent('run_start', [
            'goal' => $state->goal,
            'resume' => $resume,
            'telemetry' => $this->agentTracer()->traceContext()->toArray(),
        ]);

        for ($i = $startIteration; $i < min($this->maxIterations, $budget->maxIterations); $i++) {
            $state->setRunIteration($i);
            $iterationSpan = $this->agentTracer()->startSpan('agent.iteration', $this->traceAttributes([
                'agent.iteration' => $i + 1,
            ]), $runSpan);

            yield new AgentStreamEvent('iteration_start', ['iteration' => $i + 1]);

            if ($budget->exceededRuntime($startedAt)) {
                $result = $this->finalizeRun($runSpan, $state->response('Runtime budget reached without final answer.', $i, 'runtime_budget_exceeded', $this->budgetSnapshot($budget, $startedAt, $state)));
                $iterationSpan->setAttribute('agent.stop_reason', 'runtime_budget_exceeded');
                $iterationSpan->end('error');
                yield AgentStreamEvent::final($result);
                return $result;
            }

            try {
                $routingMessages = $this->routingMessages($state);

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
                $result = $this->finalizeRun($runSpan, $state->response('Routing model error: ' . $e->getMessage(), $i + 1, 'model_exception', $this->budgetSnapshot($budget, $startedAt, $state)));
                $iterationSpan->setAttribute('agent.stop_reason', 'model_exception');
                $iterationSpan->end('error', $e);
                yield new AgentStreamEvent('error', ['message' => $e->getMessage()]);
                yield AgentStreamEvent::final($result);
                return $result;
            }

            $decision = AgentDecision::fromArray($decisionRaw);
            $iterationSpan->setAttribute('agent.decision.type', $decision->type);
            if ($decision->confidence !== null) {
                $iterationSpan->setAttribute('agent.decision.confidence', $decision->confidence);
            }
            if ($this->model instanceof UsageAwareToolRoutingModelInterface) {
                $state->addUsage($this->model->lastUsage());
                $usageStop = $budget->exceededUsage($state->usage());
                if ($usageStop !== null) {
                    $result = $this->finalizeRun($runSpan, $state->response('Usage budget reached without final answer.', $i + 1, $usageStop, $this->budgetSnapshot($budget, $startedAt, $state)));
                    $iterationSpan->setAttribute('agent.stop_reason', $usageStop);
                    $iterationSpan->end('error');
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
                $result = $this->finalizeRun($runSpan, $terminal);
                $iterationSpan->setAttribute('agent.stop_reason', $result['stop_reason'] ?? 'final');
                $iterationSpan->end();
                yield AgentStreamEvent::final($result);
                return $result;
            }

            if ($decision->type === 'plan') {
                $planner->recordPlan($state, $decision->content);
                $iterationSpan->end();
                continue;
            }

            if ($decision->type === 'handoff') {
                yield new AgentStreamEvent('handoff_start', [
                    'iteration' => $i + 1,
                    'agent' => $decision->handoffTarget,
                    'task' => $decision->content,
                ]);
                $handoffSpan = $this->agentTracer()->startSpan('agent.handoff', $this->traceAttributes([
                    'agent.handoff.target' => $decision->handoffTarget ?? '',
                    'agent.handoff.task' => $this->truncateGoal($decision->content),
                ]), $iterationSpan);
                $this->executeHandoff($state, $decision);
                $handoffSpan->end();
                yield new AgentStreamEvent('handoff_result', [
                    'iteration' => $i + 1,
                    'agent' => $decision->handoffTarget,
                ]);
                $planner->recordReflection($state, 'Observed specialist handoff result; synthesize final answer or delegate again.');
                yield new AgentStreamEvent('reflect', ['iteration' => $i + 1]);
                $iterationSpan->end();
                continue;
            }

            if ($decision->type !== 'tool_call' && $decision->type !== 'tool_calls') {
                $state->addMessage('assistant', 'Invalid decision type; provide final answer.');
                $iterationSpan->end('error');
                continue;
            }

            $toolCalls = $decision->toolCalls;
            if ($this->orderToolCallsByRisk && count($toolCalls) > 1) {
                $toolCalls = ToolCallBatchPlanner::orderByRisk($toolCalls, $this->tools);
            }

            if (count($toolCalls) > 1) {
                yield new AgentStreamEvent('tool_batch_start', ['count' => count($toolCalls), 'ordered_by_risk' => $this->orderToolCallsByRisk]);
            }

            $parallelRunner = new ParallelToolCallRunner($this->parallelToolCalls, $this->parallelAutoloadPath);
            $normalizedCalls = array_values(array_map(
                static fn (array $call): array => [
                    'tool' => (string) ($call['tool'] ?? ''),
                    'input' => is_array($call['input'] ?? null) ? $call['input'] : [],
                ],
                $toolCalls,
            ));
            $parallelHandled = false;

            if ($parallelRunner->canParallelizeBatch($normalizedCalls, $this->tools)) {
                foreach ($toolCalls as $requestedCall) {
                    if (count($state->legacyToolCalls()) >= $toolCallBudget) {
                        $result = $this->finalizeRun($runSpan, $state->response('Tool call budget reached without final answer.', $i + 1, 'tool_budget_exceeded', $this->budgetSnapshot($budget, $startedAt, $state)));
                        $iterationSpan->setAttribute('agent.stop_reason', 'tool_budget_exceeded');
                        $iterationSpan->end('error');
                        yield AgentStreamEvent::final($result);
                        return $result;
                    }

                    $approvalStop = $this->pauseForApprovalIfNeeded($state, $requestedCall, $executor, $i + 1, $budget, $startedAt, $runSpan, $iterationSpan);
                    if ($approvalStop !== null) {
                        yield new AgentStreamEvent('approval_required', $approvalStop['event']);
                        yield AgentStreamEvent::final($approvalStop['result']);
                        return $approvalStop['result'];
                    }
                }

                $batchStarted = microtime(true);
                $batch = $parallelRunner->run(
                    $normalizedCalls,
                    $this->tools,
                    fn (array $call): string => $this->tools[$call['tool']]->invoke($call['input']),
                );

                if ($batch['mode'] === 'parallel_ext') {
                    $parallelHandled = true;
                    yield new AgentStreamEvent('tool_batch_parallel', ['mode' => $batch['mode'], 'count' => count($toolCalls)]);
                    foreach (array_values($toolCalls) as $index => $requestedCall) {
                        $toolName = (string) ($requestedCall['tool'] ?? '');
                        $toolInput = is_array($requestedCall['input'] ?? null) ? $requestedCall['input'] : [];
                        $providerCallId = isset($requestedCall['provider_call_id']) ? (string) $requestedCall['provider_call_id'] : '';
                        $riskLevel = isset($this->tools[$toolName]) && $this->tools[$toolName] instanceof ToolSchemaInterface
                            ? $this->tools[$toolName]->riskLevel()
                            : 'medium';
                        $durationMs = (int) round((microtime(true) - $batchStarted) * 1000);

                        $toolSpan = $this->agentTracer()->startSpan('agent.tool_call', $this->traceAttributes([
                            'tool.name' => $toolName,
                            'tool.risk_level' => $riskLevel,
                            'tool.parallel' => true,
                        ]), $iterationSpan);

                        yield new AgentStreamEvent('tool_start', ['tool' => $toolName, 'input' => $toolInput, 'parallel' => true]);
                        $structured = $this->finalizeToolCall($state, $toolName, $toolInput, $providerCallId, $executor, $batch['outputs'][$index] ?? '', $durationMs);
                        $toolSpan->setAttribute('tool.ok', (bool) ($structured['ok'] ?? false));
                        $toolSpan->setAttribute('tool.duration_ms', (int) ($structured['duration_ms'] ?? 0));
                        if (isset($structured['error_type'])) {
                            $toolSpan->setAttribute('tool.error_type', (string) $structured['error_type']);
                        }
                        $toolSpan->end(($structured['ok'] ?? false) ? 'ok' : 'error');
                        yield new AgentStreamEvent('tool_result', $structured);
                        $planner->observeToolResult($state, $toolName, (bool) ($structured['ok'] ?? false));
                        $this->appendToolMessages($state, $toolName, $toolInput, $structured, $providerCallId);
                    }
                }
            }

            if (!$parallelHandled) {
            foreach ($toolCalls as $requestedCall) {
                if (count($state->legacyToolCalls()) >= $toolCallBudget) {
                    $result = $this->finalizeRun($runSpan, $state->response('Tool call budget reached without final answer.', $i + 1, 'tool_budget_exceeded', $this->budgetSnapshot($budget, $startedAt, $state)));
                    $iterationSpan->setAttribute('agent.stop_reason', 'tool_budget_exceeded');
                    $iterationSpan->end('error');
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
                    $pause = $this->pauseForApprovalIfNeeded($state, $requestedCall, $executor, $i + 1, $budget, $startedAt, $runSpan, $iterationSpan);
                    if ($pause !== null) {
                        yield new AgentStreamEvent('approval_required', $pause['event']);
                        yield AgentStreamEvent::final($pause['result']);
                        return $pause['result'];
                    }
                }

                $toolSpan = $this->agentTracer()->startSpan('agent.tool_call', $this->traceAttributes([
                    'tool.name' => $toolName,
                    'tool.risk_level' => $riskLevel,
                ]), $iterationSpan);

                yield new AgentStreamEvent('tool_start', ['tool' => $toolName, 'input' => $toolInput]);
                $structured = $this->executeToolCall($state, $toolName, $toolInput, $providerCallId, $executor);
                $toolSpan->setAttribute('tool.ok', (bool) ($structured['ok'] ?? false));
                $toolSpan->setAttribute('tool.duration_ms', (int) ($structured['duration_ms'] ?? 0));
                if (isset($structured['error_type'])) {
                    $toolSpan->setAttribute('tool.error_type', (string) $structured['error_type']);
                }
                $toolSpan->end(($structured['ok'] ?? false) ? 'ok' : 'error');
                yield new AgentStreamEvent('tool_result', $structured);
                $planner->observeToolResult($state, $toolName, (bool) ($structured['ok'] ?? false));
                $this->appendToolMessages($state, $toolName, $toolInput, $structured, $providerCallId);
            }
            }

            if (count($toolCalls) > 1) {
                yield new AgentStreamEvent('tool_batch_complete', ['count' => count($toolCalls)]);
            }

            $planner->recordReflection($state, 'Observed tool results; decide whether to continue or final answer.');
            yield new AgentStreamEvent('reflect', ['iteration' => $i + 1]);
            $iterationSpan->end();
        }

        $result = $this->finalizeRun($runSpan, $state->response('Max iterations reached without final answer.', min($this->maxIterations, $budget->maxIterations), 'max_iterations', $this->budgetSnapshot($budget, $startedAt, $state)));
        yield AgentStreamEvent::final($result);
        return $result;
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function finalizeRun(AgentSpanScopeInterface $runSpan, array $result): array
    {
        $runSpan->setAttribute('agent.stop_reason', (string) ($result['stop_reason'] ?? ''));
        $runSpan->setAttribute('agent.iterations', (int) ($result['iterations'] ?? 0));
        $runSpan->setAttribute('agent.tool_call_count', count(is_array($result['tool_calls'] ?? null) ? $result['tool_calls'] : []));
        $runSpan->setAttribute('agent.handoff_count', count(is_array($result['handoffs'] ?? null) ? $result['handoffs'] : []));
        $runSpan->end();

        $telemetry = $this->agentTracer()->traceContext();
        if (!$telemetry->isEmpty()) {
            $result['telemetry'] = $telemetry->toArray();
        }

        $this->agentRunLogger()->log(AgentRunLogEntry::fromAgentResult(
            $result,
            $this->agentName,
            $this->runLogContext,
            $this->traceRedactor,
        ));

        return $result;
    }

    /**
     * @param array<string, bool|float|int|string|null> $attributes
     * @return array<string, bool|float|int|string|null>
     */
    private function traceAttributes(array $attributes): array
    {
        return ($this->traceRedactor ?? new TraceRedactor())->redactArray($attributes);
    }

    private function truncateGoal(string $goal, int $maxLength = 240): string
    {
        $goal = trim($goal);
        if (strlen($goal) <= $maxLength) {
            return $goal;
        }

        return substr($goal, 0, $maxLength - 3) . '...';
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

    /** @param array<string, mixed> $requestedCall @return array{result: array<string, mixed>, event: array<string, mixed>}|null */
    private function pauseForApprovalIfNeeded(
        AgentState $state,
        array $requestedCall,
        ToolExecutor $executor,
        int $iteration,
        AgentBudget $budget,
        float $startedAt,
        AgentSpanScopeInterface $runSpan,
        AgentSpanScopeInterface $iterationSpan,
    ): ?array {
        $toolName = (string) ($requestedCall['tool'] ?? '');
        $toolInput = is_array($requestedCall['input'] ?? null) ? $requestedCall['input'] : [];
        $providerCallId = isset($requestedCall['provider_call_id']) ? (string) $requestedCall['provider_call_id'] : '';
        $riskLevel = isset($this->tools[$toolName]) && $this->tools[$toolName] instanceof ToolSchemaInterface
            ? $this->tools[$toolName]->riskLevel()
            : 'medium';

        if (!isset($this->tools[$toolName]) || !$executor->policy()->shouldPauseForApproval($toolName, $riskLevel, $toolInput)) {
            return null;
        }

        $approvalToken = $executor->policy()->approvalToken($toolName, $toolInput);
        $state->setPendingApproval([
            'tool' => $toolName,
            'input' => $toolInput,
            'provider_call_id' => $providerCallId,
            'risk_level' => $riskLevel,
            'approval_token' => $approvalToken,
            'iteration' => $iteration,
        ]);
        $state->setRunIteration($iteration);
        $answer = sprintf('Approval required before executing high-risk tool: %s', $toolName);
        $result = $this->finalizeRun($runSpan, $state->response($answer, $iteration, 'awaiting_approval', $this->budgetSnapshot($budget, $startedAt, $state), $approvalToken));
        $iterationSpan->setAttribute('agent.stop_reason', 'awaiting_approval');
        $iterationSpan->end();

        return [
            'result' => $result,
            'event' => [
                'tool' => $toolName,
                'risk_level' => $riskLevel,
                'approval_token' => $approvalToken,
            ],
        ];
    }

    /** @param array<string, mixed> $toolInput @return array<string, mixed> */
    private function executeToolCall(
        AgentState $state,
        string $toolName,
        array $toolInput,
        string $providerCallId,
        ToolExecutor $executor,
        bool $approvalGranted = false,
    ): array {
        return $this->finalizeToolCall($state, $toolName, $toolInput, $providerCallId, $executor, null, 0, $approvalGranted);
    }

    /** @param array<string, mixed> $toolInput @return array<string, mixed> */
    private function finalizeToolCall(
        AgentState $state,
        string $toolName,
        array $toolInput,
        string $providerCallId,
        ToolExecutor $executor,
        ?string $precomputedOutput,
        int $durationMs = 0,
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
        } elseif ($precomputedOutput !== null) {
            $preflight = $executor->preflight($this->tools[$toolName], $toolInput, $approvalGranted);
            if ($preflight !== null) {
                $result = $preflight;
            } else {
                $result = $executor->adoptInvokedOutput($this->tools[$toolName], $toolInput, $precomputedOutput, $durationMs);
            }
            $output = $result->output;
            $structured = $result->toArray();
        } else {
            $result = $executor->execute($this->tools[$toolName], $toolInput, $approvalGranted);
            $output = $result->output;
            $structured = $result->toArray();
        }

        if ($providerCallId !== '') {
            $structured['provider_call_id'] = $providerCallId;
        }

        $state->recordToolCall(['name' => $toolName, 'input' => $toolInput, 'output' => $output], $structured);
        $this->recordMemoryEpisode($toolName, $structured);

        return $structured;
    }

    /** @param array<int, array<string, mixed>> $messages @return array<int, array<string, mixed>> */
    private function routingMessages(AgentState $state): array
    {
        $messages = $this->contextManager === null
            ? $state->messages()
            : $this->contextManager->prepareForRouting($state->messages());

        if ($this->memoryStore === null || $this->memorySessionId === null || $this->memorySessionId === '') {
            return $messages;
        }

        $windowed = false;
        foreach ($messages as $message) {
            if (($message['role'] ?? '') === 'system'
                && str_contains((string) ($message['content'] ?? ''), 'earlier messages omitted')) {
                $windowed = true;
                break;
            }
        }

        if (!$windowed && count($state->messages()) < 12) {
            return $messages;
        }

        $summary = $this->memoryStore->summarizeForContext($this->memorySessionId);
        if ($summary === '') {
            return $messages;
        }

        $headCount = min(1, count($messages));
        $head = array_slice($messages, 0, $headCount);
        $tail = array_slice($messages, $headCount);

        return array_merge($head, [['role' => 'system', 'content' => $summary]], $tail);
    }

    /** @param array<string, mixed> $structured */
    private function recordMemoryEpisode(string $toolName, array $structured): void
    {
        if ($this->memoryStore === null || $this->memorySessionId === null || $this->memorySessionId === '') {
            return;
        }

        $data = is_array($structured['data'] ?? null) ? $structured['data'] : [];
        $note = is_string($data['message'] ?? null)
            ? (string) $data['message']
            : (is_string($structured['error'] ?? null) ? (string) $structured['error'] : '');

        $this->memoryStore->append($this->memorySessionId, [
            'tool' => $toolName,
            'ok' => (bool) ($structured['ok'] ?? false),
            'note' => $note,
            'idempotent_replay' => (bool) ($structured['idempotent_replay'] ?? false),
        ]);
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
