<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

final class ToolExecutionResult
{
    /**
     * @param array<string, mixed> $input
     * @param mixed $data
     */
    public function __construct(
        public readonly bool $ok,
        public readonly string $tool,
        public readonly array $input,
        public readonly string $output,
        public readonly mixed $data,
        public readonly int $durationMs,
        public readonly ?string $error = null,
        public readonly string $errorType = '',
        public readonly bool $truncated = false,
        public readonly int $attempts = 1,
    ) {
    }

    /**
     * @return array{ok: bool, tool: string, input: array<string, mixed>, output: string, data: mixed, duration_ms: int, error: ?string, error_type: string, truncated: bool, attempts: int}
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'tool' => $this->tool,
            'input' => $this->input,
            'output' => $this->output,
            'data' => $this->data,
            'duration_ms' => $this->durationMs,
            'error' => $this->error,
            'error_type' => $this->errorType,
            'truncated' => $this->truncated,
            'attempts' => $this->attempts,
        ];
    }
}

