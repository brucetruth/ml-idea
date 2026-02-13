<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Tools;

use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Explorer\DocumentExplorerService;

final class CorpusStatsTool implements ToolInterface
{
    public function __construct(private readonly DocumentExplorerService $service)
    {
    }

    public function name(): string { return 'corpus_stats'; }
    public function description(): string { return 'Returns corpus/source statistics and source list.'; }

    public function invoke(array $input): string
    {
        return json_encode([
            'ok' => true,
            'stats' => $this->service->corpusStats(),
            'sources' => $this->service->listSources(),
        ], JSON_THROW_ON_ERROR);
    }
}
