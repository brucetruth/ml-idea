<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Tools;

use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Explorer\DocumentExplorerService;

final class DocSearchTool implements ToolInterface
{
    public function __construct(private readonly DocumentExplorerService $service)
    {
    }

    public function name(): string { return 'doc_search'; }
    public function description(): string { return 'Search indexed local docs. Input: {"query":"...","topK":5,"filters":{"source":"notes"},"envelope":true}'; }

    public function invoke(array $input): string
    {
        $query = isset($input['query']) ? trim((string) $input['query']) : '';
        $topK = isset($input['topK']) ? max(1, (int) $input['topK']) : 5;
        $filters = isset($input['filters']) && is_array($input['filters']) ? $input['filters'] : [];
        $envelope = (bool) ($input['envelope'] ?? false);
        if ($query === '') {
            return 'DocSearchTool: missing query.';
        }

        if ($envelope) {
            $result = $this->service->query($query, [
                'topK' => $topK,
                'filters' => $filters,
                'structured' => true,
            ]);
            return json_encode($result, JSON_THROW_ON_ERROR);
        }

        $hits = $this->service->search($query, $filters, $topK);
        return json_encode(['ok' => true, 'query' => $query, 'count' => count($hits), 'hits' => $hits], JSON_THROW_ON_ERROR);
    }
}
