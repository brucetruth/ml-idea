<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Contracts;

interface IdempotentToolInterface extends ToolInterface
{
    /** Stable key for deduplicating side effects (e.g. refund order #101). */
    public function idempotencyKey(array $input): string;
}
