<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Tools;

use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Explorer\DocumentExplorerService;

final class DocSummarizeTool implements ToolInterface
{
    public function __construct(private readonly DocumentExplorerService $service)
    {
    }

    public function name(): string { return 'doc_summarize'; }
    public function description(): string { return 'Extractive summary over local docs. Input: {"query":"...","maxSentences":4,"topK":6,"envelope":true}'; }

    public function invoke(array $input): string
    {
        $query = isset($input['query']) ? trim((string) $input['query']) : '';
        if ($query === '') {
            return 'DocSummarizeTool: missing query.';
        }
        $maxSentences = isset($input['maxSentences']) ? max(1, (int) $input['maxSentences']) : 4;
        $topK = isset($input['topK']) ? max(1, (int) $input['topK']) : 6;
        $envelope = (bool) ($input['envelope'] ?? false);

        if ($envelope) {
            $result = $this->service->query($query, [
                'topK' => $topK,
                'structured' => true,
            ]);
            return json_encode($result, JSON_THROW_ON_ERROR);
        }

        $summary = $this->service->summarize($query, $maxSentences, $topK);
        return json_encode(['ok' => true, 'query' => $query, 'summary' => $summary], JSON_THROW_ON_ERROR);
    }
}
