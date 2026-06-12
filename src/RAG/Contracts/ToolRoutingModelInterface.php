<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Contracts;

interface ToolRoutingModelInterface
{
    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<int, array<string, mixed>> $tools
     * @return array<string, mixed>
     */
    public function respond(array $messages, array $tools): array;
}
