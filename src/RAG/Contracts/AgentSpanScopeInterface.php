<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Contracts;

interface AgentSpanScopeInterface
{
    public function spanId(): string;

    /** @param bool|float|int|string|null $value */
    public function setAttribute(string $key, mixed $value): void;

    public function end(string $status = 'ok', ?\Throwable $error = null): void;
}
