<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\MCP;

interface McpTransportInterface
{
    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function send(array $request): array;
}
