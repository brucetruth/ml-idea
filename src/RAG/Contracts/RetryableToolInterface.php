<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Contracts;

interface RetryableToolInterface extends ToolInterface
{
    public function isRetryable(): bool;
}
