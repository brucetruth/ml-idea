<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Contracts;

interface HttpTransportInterface
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $jsonBody
     * @return array<string, mixed>
     */
    public function postJson(string $url, array $headers, array $jsonBody): array;
}
