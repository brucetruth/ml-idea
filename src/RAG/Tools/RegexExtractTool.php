<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Tools;

use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Explorer\DocumentExplorerService;

final class RegexExtractTool implements ToolInterface
{
    public function __construct(private readonly DocumentExplorerService $service)
    {
    }

    public function name(): string { return 'regex_extract'; }
    public function description(): string { return 'Extract regex matches from corpus. Input: {"pattern":"/.../","limit":50}'; }

    public function invoke(array $input): string
    {
        $pattern = isset($input['pattern']) ? (string) $input['pattern'] : '';
        $limit = isset($input['limit']) ? max(1, (int) $input['limit']) : 50;
        if ($pattern === '') {
            return 'RegexExtractTool: missing pattern.';
        }

        return json_encode([
            'ok' => true,
            'pattern' => $pattern,
            'matches' => $this->service->regexExtract($pattern, $limit),
        ], JSON_THROW_ON_ERROR);
    }
}
