<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

final class TraceRedactor
{
    /** @param array<int, string> $sensitiveKeys */
    public function __construct(private readonly array $sensitiveKeys = ['api_key', 'apikey', 'token', 'authorization', 'password', 'secret'])
    {
    }

    /** @param array<string, mixed> $value */
    public function redactArray(array $value): array
    {
        $out = [];
        foreach ($value as $key => $item) {
            if ($this->isSensitive((string) $key)) {
                $out[$key] = '[redacted]';
                continue;
            }
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $out[$key] = $this->redactArray($item);
                continue;
            }
            $out[$key] = is_string($item) ? $this->redactString($item) : $item;
        }

        return $out;
    }

    public function redactString(string $value): string
    {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return json_encode($this->redactArray($decoded), JSON_THROW_ON_ERROR);
        }

        $value = preg_replace('/(Bearer\s+)[A-Za-z0-9._\-]+/i', '$1[redacted]', $value) ?? $value;
        return preg_replace('/([?&](?:api_key|apikey|token|password|secret)=)[^&\s]+/i', '$1[redacted]', $value) ?? $value;
    }

    private function isSensitive(string $key): bool
    {
        $normalized = strtolower($key);
        foreach ($this->sensitiveKeys as $sensitiveKey) {
            if (str_contains($normalized, strtolower($sensitiveKey))) {
                return true;
            }
        }
        return false;
    }
}
