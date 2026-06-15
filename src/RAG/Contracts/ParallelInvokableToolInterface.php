<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Contracts;

/** Tools that can run in an isolated worker (no container DI). */
interface ParallelInvokableToolInterface extends ToolInterface
{
    /** @param array<string, mixed> $input */
    public static function invokeParallel(array $input): string;
}
