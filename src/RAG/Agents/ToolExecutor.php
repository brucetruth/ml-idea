<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

use ML\IDEA\RAG\Contracts\IdempotentToolInterface;
use ML\IDEA\RAG\Contracts\RetryableToolInterface;
use ML\IDEA\RAG\Contracts\ToolIdempotencyStoreInterface;
use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Contracts\ToolSchemaInterface;

final class ToolExecutor
{
    public function __construct(
        private readonly ToolInputValidator $validator = new ToolInputValidator(),
        private readonly AgentPolicy $policy = new AgentPolicy(),
        private readonly TraceRedactor $redactor = new TraceRedactor(),
        private readonly ToolReliabilityPolicy $reliability = new ToolReliabilityPolicy(),
        private readonly ?ToolIdempotencyStoreInterface $idempotencyStore = null,
        private readonly ?ToolCircuitBreaker $circuitBreaker = null,
    ) {
    }

    /** @param array<string, mixed> $input */
    public function execute(ToolInterface $tool, array $input, bool $approvalGranted = false): ToolExecutionResult
    {
        $idempotencyKey = $this->resolveIdempotencyKey($tool, $input);
        if ($idempotencyKey !== null && $this->idempotencyStore !== null) {
            $cached = $this->idempotencyStore->get($idempotencyKey);
            if ($cached !== null) {
                $replay = ToolExecutionResult::fromArray($cached);
                return new ToolExecutionResult(
                    ok: $replay->ok,
                    tool: $replay->tool,
                    input: $this->redactor->redactArray($input),
                    output: $replay->output,
                    data: $replay->data,
                    durationMs: 0,
                    error: $replay->error,
                    errorType: $replay->errorType,
                    truncated: $replay->truncated,
                    attempts: 0,
                    idempotentReplay: true,
                );
            }
        }

        $result = $this->executeOnce($tool, $input, $approvalGranted);
        $this->recordCircuitOutcome($tool->name(), $result);

        if ($idempotencyKey !== null && $this->idempotencyStore !== null && $result->ok) {
            $this->idempotencyStore->put($idempotencyKey, $result->toArray());
        }

        return $result;
    }

    /** @param array<string, mixed> $input */
    public function preflight(ToolInterface $tool, array $input, bool $approvalGranted = false): ?ToolExecutionResult
    {
        return $this->guardExecution($tool, $input, $approvalGranted);
    }

    /** @param array<string, mixed> $input */
    public function adoptInvokedOutput(ToolInterface $tool, array $input, string $raw, int $durationMs = 0): ToolExecutionResult
    {
        $toolName = $tool->name();
        $truncated = false;
        if (strlen($raw) > $this->policy->maxToolOutputBytes()) {
            $raw = substr($raw, 0, $this->policy->maxToolOutputBytes()) . '...[truncated]';
            $truncated = true;
        }

        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? $decoded : $raw;
        $result = new ToolExecutionResult(
            true,
            $toolName,
            $this->redactor->redactArray($input),
            $this->redactor->redactString($raw),
            is_array($data) ? $this->redactor->redactArray($data) : $this->redactor->redactString((string) $data),
            $durationMs,
            null,
            '',
            $truncated,
            1,
        );
        $this->recordCircuitOutcome($toolName, $result);

        return $result;
    }

    /** @param array<string, mixed> $input */
    private function executeOnce(ToolInterface $tool, array $input, bool $approvalGranted): ToolExecutionResult
    {
        $guard = $this->guardExecution($tool, $input, $approvalGranted);
        if ($guard !== null) {
            return $guard;
        }

        $toolName = $tool->name();
        $start = microtime(true);
        $attempts = 0;
        $lastError = null;
        $lastErrorType = '';

        while ($attempts < $this->reliability->maxAttempts) {
            $attempts++;
            try {
                $raw = $tool->invoke($input);
                $durationMs = (int) round((microtime(true) - $start) * 1000);
                if ($this->reliability->timeoutMs > 0 && $durationMs > $this->reliability->timeoutMs) {
                    $message = sprintf('Tool exceeded timeout budget (%dms).', $this->reliability->timeoutMs);
                    if ($this->shouldRetry($tool, $attempts, 'timeout')) {
                        $this->sleepBeforeRetry();
                        continue;
                    }

                    return new ToolExecutionResult(false, $toolName, $this->redactor->redactArray($input), $message, null, $durationMs, $message, 'timeout', false, $attempts);
                }

                $truncated = false;
                if (strlen($raw) > $this->policy->maxToolOutputBytes()) {
                    $raw = substr($raw, 0, $this->policy->maxToolOutputBytes()) . '...[truncated]';
                    $truncated = true;
                }
                $decoded = json_decode($raw, true);
                $data = is_array($decoded) ? $decoded : $raw;

                return new ToolExecutionResult(
                    true,
                    $toolName,
                    $this->redactor->redactArray($input),
                    $this->redactor->redactString($raw),
                    is_array($data) ? $this->redactor->redactArray($data) : $this->redactor->redactString((string) $data),
                    $durationMs,
                    null,
                    '',
                    $truncated,
                    $attempts,
                );
            } catch (\Throwable $e) {
                $durationMs = (int) round((microtime(true) - $start) * 1000);
                $lastError = 'Tool exception: ' . $e->getMessage();
                $lastErrorType = 'tool_exception';
                if ($this->shouldRetry($tool, $attempts, $lastErrorType)) {
                    $this->sleepBeforeRetry();
                    continue;
                }
            }
        }

        $durationMs = (int) round((microtime(true) - $start) * 1000);
        $message = $lastError ?? 'Tool execution failed.';
        return new ToolExecutionResult(false, $toolName, $this->redactor->redactArray($input), $message, null, $durationMs, $message, $lastErrorType !== '' ? $lastErrorType : 'tool_exception', false, $attempts);
    }

    private function shouldRetry(ToolInterface $tool, int $attempts, string $errorType): bool
    {
        if ($attempts >= $this->reliability->maxAttempts) {
            return false;
        }

        if (!in_array($errorType, ['tool_exception', 'timeout'], true)) {
            return false;
        }

        return $tool instanceof RetryableToolInterface && $tool->isRetryable();
    }

    private function sleepBeforeRetry(): void
    {
        if ($this->reliability->retryDelayMs <= 0) {
            return;
        }

        usleep($this->reliability->retryDelayMs * 1000);
    }

    public function reliability(): ToolReliabilityPolicy
    {
        return $this->reliability;
    }

    public function policy(): AgentPolicy
    {
        return $this->policy;
    }

    /** @param array<string, mixed> $input */
    private function resolveIdempotencyKey(ToolInterface $tool, array $input): ?string
    {
        if (!$tool instanceof IdempotentToolInterface) {
            return null;
        }

        $key = trim($tool->idempotencyKey($input));

        return $key !== '' ? $tool->name() . ':' . $key : null;
    }

    /** @param array<string, mixed> $input */
    private function guardExecution(ToolInterface $tool, array $input, bool $approvalGranted): ?ToolExecutionResult
    {
        $toolName = $tool->name();

        if ($this->circuitBreaker !== null && $this->circuitBreaker->isOpen($toolName)) {
            $message = sprintf('Tool circuit breaker open for %s; try again later.', $toolName);

            return new ToolExecutionResult(false, $toolName, $this->redactor->redactArray($input), $message, null, 0, $message, 'circuit_open');
        }

        $riskLevel = $tool instanceof ToolSchemaInterface ? $tool->riskLevel() : 'medium';
        if (!$approvalGranted) {
            $policyError = $this->policy->canUseTool($toolName, $riskLevel, $input);
            if ($policyError !== null) {
                return new ToolExecutionResult(false, $toolName, $this->redactor->redactArray($input), $policyError, null, 0, $policyError, 'policy_block');
            }
        }

        $schema = $tool instanceof ToolSchemaInterface ? $tool->inputSchema() : [];
        $validationErrors = $this->validator->validate($schema, $input);
        if ($validationErrors !== []) {
            $message = 'Tool input validation failed: ' . implode(' ', $validationErrors);

            return new ToolExecutionResult(false, $toolName, $this->redactor->redactArray($input), $message, ['validation_errors' => $validationErrors], 0, $message, 'validation_error');
        }

        return null;
    }

    private function recordCircuitOutcome(string $toolName, ToolExecutionResult $result): void
    {
        if ($this->circuitBreaker === null) {
            return;
        }

        if ($result->ok) {
            $this->circuitBreaker->recordSuccess($toolName);

            return;
        }

        if (in_array($result->errorType, ['tool_exception', 'timeout'], true)) {
            $this->circuitBreaker->recordFailure($toolName);
        }
    }

    public function circuitBreaker(): ?ToolCircuitBreaker
    {
        return $this->circuitBreaker;
    }
}
