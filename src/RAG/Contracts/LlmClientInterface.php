<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Contracts;

interface LlmClientInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function generate(string $prompt, array $options = []): string;
}
