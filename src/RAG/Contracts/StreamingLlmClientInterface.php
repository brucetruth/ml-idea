<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Contracts;

interface StreamingLlmClientInterface extends LlmClientInterface
{
    /**
     * @param array<string, mixed> $options
     * @return iterable<int, string>
     */
    public function streamGenerate(string $prompt, array $options = []): iterable;
}
