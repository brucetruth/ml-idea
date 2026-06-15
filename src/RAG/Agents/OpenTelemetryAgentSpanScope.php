<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

use ML\IDEA\RAG\Contracts\AgentSpanScopeInterface;

/** @internal */
final class OpenTelemetryAgentSpanScope implements AgentSpanScopeInterface
{
    /** @var object SpanInterface */
    private readonly object $span;

    /** @var object|null ScopeInterface */
    private ?object $scope = null;

    /** @param object $span SpanInterface */
    public function __construct(object $span)
    {
        $this->span = $span;
        if (method_exists($span, 'activate')) {
            $this->scope = $span->activate();
        }
    }

    public function spanId(): string
    {
        if (!method_exists($this->span, 'getContext')) {
            return '';
        }

        $context = $this->span->getContext();

        return method_exists($context, 'getSpanId') ? (string) $context->getSpanId() : '';
    }

    public function setAttribute(string $key, mixed $value): void
    {
        if ($value === null || !method_exists($this->span, 'setAttribute')) {
            return;
        }

        $this->span->setAttribute($key, $value);
    }

    public function end(string $status = 'ok', ?\Throwable $error = null): void
    {
        if ($error !== null && method_exists($this->span, 'recordException')) {
            $this->span->recordException($error);
        }

        if ($status !== 'ok' && method_exists($this->span, 'setStatus')) {
            $statusCodeClass = 'OpenTelemetry\\API\\Trace\\StatusCode';
            if (class_exists($statusCodeClass) && defined($statusCodeClass . '::STATUS_ERROR')) {
                $this->span->setStatus(constant($statusCodeClass . '::STATUS_ERROR'), $error?->getMessage() ?? $status);
            }
        }

        if (method_exists($this->span, 'end')) {
            $this->span->end();
        }

        if ($this->scope !== null && method_exists($this->scope, 'detach')) {
            $this->scope->detach();
            $this->scope = null;
        }
    }
}
