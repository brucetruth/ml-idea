<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Http;

use ML\IDEA\Exceptions\SerializationException;
use ML\IDEA\RAG\Contracts\HttpTransportInterface;

final class SimpleHttpTransport implements HttpTransportInterface
{
    public function postJson(string $url, array $headers, array $jsonBody): array
    {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = sprintf('%s: %s', $name, $value);
        }
        $headerLines[] = 'Content-Type: application/json';

        $payload = json_encode($jsonBody, JSON_THROW_ON_ERROR);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headerLines),
                'content' => $payload,
                'ignore_errors' => true,
                'timeout' => 60,
            ],
        ]);

        $result = @file_get_contents($url, false, $context);
        if ($result === false) {
            throw new SerializationException(sprintf('HTTP POST failed for URL: %s', $url));
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
        return $decoded;
    }
}
