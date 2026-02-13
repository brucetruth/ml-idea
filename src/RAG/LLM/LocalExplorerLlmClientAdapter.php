<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\LLM;

use ML\IDEA\RAG\Contracts\LlmClientInterface;
use ML\IDEA\RAG\Contracts\StreamingLlmClientInterface;
use ML\IDEA\RAG\Explorer\DocumentExplorerService;

final class LocalExplorerLlmClientAdapter implements LlmClientInterface, StreamingLlmClientInterface
{
    public function __construct(private readonly DocumentExplorerService $service = new DocumentExplorerService())
    {
    }

    /** @param array<string,mixed> $options */
    public function generate(string $prompt, array $options = []): string
    {
        return $this->service->generate($prompt, $options);
    }

    /** @param array<string,mixed> $options
     * @return iterable<int,string>
     */
    public function streamGenerate(string $prompt, array $options = []): iterable
    {
        return $this->service->streamGenerate($prompt, $options);
    }
}
