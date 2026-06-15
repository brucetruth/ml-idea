<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\RAG\Contracts\AgentSpanScopeInterface;
use ML\IDEA\RAG\Contracts\AgentTracerInterface;

/**
 * Bridges ToolRoutingAgent spans to an OpenTelemetry tracer.
 *
 * Requires open-telemetry/sdk (or compatible TracerInterface implementation).
 */
final class OpenTelemetryAgentTracer implements AgentTracerInterface
{
    private readonly TraceRedactor $redactor;

    /** @var object TracerInterface */
    private readonly object $tracer;

    private ?string $traceId = null;

    private ?string $rootSpanId = null;

    public function __construct(object $tracer, ?TraceRedactor $redactor = null)
    {
        if (!interface_exists('OpenTelemetry\\API\\Trace\\TracerInterface')) {
            throw new InvalidArgumentException(
                'OpenTelemetryAgentTracer requires open-telemetry/sdk. Install with: composer require open-telemetry/sdk'
            );
        }

        if (!is_a($tracer, 'OpenTelemetry\\API\\Trace\\TracerInterface')) {
            throw new InvalidArgumentException('Tracer must implement OpenTelemetry\\API\\Trace\\TracerInterface.');
        }

        $this->tracer = $tracer;
        $this->redactor = $redactor ?? new TraceRedactor();
    }

    public function traceContext(): AgentTraceContext
    {
        if ($this->traceId === null || $this->rootSpanId === null) {
            return new AgentTraceContext();
        }

        return new AgentTraceContext($this->traceId, $this->rootSpanId);
    }

    public function startSpan(string $name, array $attributes = [], ?AgentSpanScopeInterface $parent = null): AgentSpanScopeInterface
    {
        $builder = $this->tracer->spanBuilder($name);
        foreach ($this->redactor->redactArray($attributes) as $key => $value) {
            if ($value === null) {
                continue;
            }
            $builder->setAttribute((string) $key, $value);
        }

        $span = $builder->startSpan();
        $context = $span->getContext();
        $traceId = $context->getTraceId();
        $spanId = $context->getSpanId();

        if ($this->traceId === null && $traceId !== '') {
            $this->traceId = $traceId;
        }
        if ($parent === null && $spanId !== '') {
            $this->rootSpanId = $spanId;
        }

        return new OpenTelemetryAgentSpanScope($span);
    }
}
