<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

use ML\IDEA\RAG\Contracts\AgentSpanScopeInterface;

final class RecordingAgentSpanScope implements AgentSpanScopeInterface
{
    /** @var array<string, bool|float|int|string|null> */
    private array $attributes;

    private float $startedAt;

    private bool $ended = false;

    /**
     * @param array<string, bool|float|int|string|null> $attributes
     */
    public function __construct(
        private readonly RecordingAgentTracer $tracer,
        private readonly string $name,
        private readonly string $spanId,
        private readonly ?string $parentSpanId,
        array $attributes,
    ) {
        $this->attributes = $attributes;
        $this->startedAt = microtime(true);
    }

    public function spanId(): string
    {
        return $this->spanId;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        if ($this->ended) {
            return;
        }

        if (is_bool($value) || is_float($value) || is_int($value) || is_string($value) || $value === null) {
            $this->attributes[$key] = $value;
        }
    }

    public function end(string $status = 'ok', ?\Throwable $error = null): void
    {
        if ($this->ended) {
            return;
        }

        $this->ended = true;
        $record = [
            'name' => $this->name,
            'span_id' => $this->spanId,
            'parent_span_id' => $this->parentSpanId,
            'status' => $status,
            'duration_ms' => (int) round((microtime(true) - $this->startedAt) * 1000),
            'attributes' => $this->attributes,
        ];

        if ($error !== null) {
            $record['error'] = $error->getMessage();
        }

        $this->tracer->recordEnded($record);
    }
}
