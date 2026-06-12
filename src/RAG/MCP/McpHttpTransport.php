<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\MCP;

use ML\IDEA\RAG\Contracts\HttpTransportInterface;
use ML\IDEA\RAG\Http\SimpleHttpTransport;

final class McpHttpTransport implements McpTransportInterface
{
    /** @param array<string, string> $headers */
    public function __construct(
        private readonly string $endpoint,
        private readonly HttpTransportInterface $http = new SimpleHttpTransport(),
        private readonly array $headers = [],
    ) {
    }

    public function send(array $request): array
    {
        /** @var array<string, mixed> $response */
        $response = $this->http->postJson($this->endpoint, $this->headers, $request);

        return $response;
    }
}
