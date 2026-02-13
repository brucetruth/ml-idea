<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Contracts;

interface ToolInterface
{
    public function name(): string;

    public function description(): string;

    /**
     * @param array<string, mixed> $input
     */
    public function invoke(array $input): string;
}
