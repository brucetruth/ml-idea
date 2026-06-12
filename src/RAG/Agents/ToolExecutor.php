<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

use ML\IDEA\RAG\Contracts\RetryableToolInterface;
use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Contracts\ToolSchemaInterface;

final class ToolExecutor
{
    public function __construct(
        private readonly ToolInputValidator $validator = new ToolInputValidator(),
        private readonly AgentPolicy $policy = new AgentPolicy(),
        private readonly TraceRedactor $redactor = new TraceRedactor(),
        private readonly ToolReliabilityPolicy $reliability = new ToolReliabilityPolicy(),
    ) {
    }

    /** @param array<string, mixed> $input */
    public function execute(ToolInterface $tool, array $input, bool $approvalGranted = false): ToolExecutionResult
    {
        $toolName = $tool->name();
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
}
