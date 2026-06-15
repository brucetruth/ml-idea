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
        public readonly bool $idempotentReplay = false,
    ) {
    }

    /**
     * @return array{ok: bool, tool: string, input: array<string, mixed>, output: string, data: mixed, duration_ms: int, error: ?string, error_type: string, truncated: bool, attempts: int, idempotent_replay: bool}
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
            'idempotent_replay' => $this->idempotentReplay,
        ];
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        return new self(
            ok: (bool) ($payload['ok'] ?? false),
            tool: (string) ($payload['tool'] ?? ''),
            input: is_array($payload['input'] ?? null) ? $payload['input'] : [],
            output: (string) ($payload['output'] ?? ''),
            data: $payload['data'] ?? null,
            durationMs: (int) ($payload['duration_ms'] ?? 0),
            error: isset($payload['error']) ? (string) $payload['error'] : null,
            errorType: (string) ($payload['error_type'] ?? ''),
            truncated: (bool) ($payload['truncated'] ?? false),
            attempts: (int) ($payload['attempts'] ?? 1),
            idempotentReplay: (bool) ($payload['idempotent_replay'] ?? false),
        );
    }
}

