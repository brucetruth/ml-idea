<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Tools;

use ML\IDEA\RAG\Contracts\ToolInterface;

final class FreeApiGetTool implements ToolInterface
{
    /**
     * @param array<int, string> $allowedPrefixes
     */
    public function __construct(private readonly array $allowedPrefixes = [])
    {
    }

    public function name(): string
    {
        return 'free_api';
    }

    public function description(): string
    {
        return 'Performs GET requests against approved free API endpoints.';
    }

    public function invoke(array $input): string
    {
        $url = isset($input['url']) ? trim((string) $input['url']) : '';
        if ($url === '') {
            return 'FreeApiGetTool: missing url.';
        }

        if (!str_starts_with($url, 'https://') && !str_starts_with($url, 'http://')) {
            return 'FreeApiGetTool: url must start with http:// or https://';
        }

        if ($this->allowedPrefixes !== []) {
            $allowed = false;
            foreach ($this->allowedPrefixes as $prefix) {
                if (str_starts_with($url, $prefix)) {
                    $allowed = true;
                    break;
                }
            }

            if (!$allowed) {
                return 'FreeApiGetTool: URL not in allow-list.';
            }
        }

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 20,
                'header' => "User-Agent: ml-idea-rag-tool\r\n",
            ],
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            return 'FreeApiGetTool: request failed.';
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return json_encode($decoded, JSON_THROW_ON_ERROR);
        }

        return $raw;
    }
}
