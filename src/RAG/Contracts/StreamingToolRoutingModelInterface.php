<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Contracts;

interface StreamingToolRoutingModelInterface extends ToolRoutingModelInterface
{
    /**
     * @param array<int, array<string, mixed>> $messages
     * @param array<int, array<string, mixed>> $tools
     * @return iterable<int, string>
     */
    public function streamRespond(array $messages, array $tools): iterable;
}
