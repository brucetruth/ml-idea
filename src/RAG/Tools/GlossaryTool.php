<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Tools;

use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Explorer\DocumentExplorerService;

final class GlossaryTool implements ToolInterface
{
    public function __construct(private readonly DocumentExplorerService $service)
    {
    }

    public function name(): string { return 'glossary'; }
    public function description(): string { return 'Build corpus glossary. Input: {"topN":12}'; }

    public function invoke(array $input): string
    {
        $topN = isset($input['topN']) ? max(1, (int) $input['topN']) : 12;
        return json_encode([
            'ok' => true,
            'topN' => $topN,
            'glossary' => $this->service->glossary($topN),
        ], JSON_THROW_ON_ERROR);
    }
}
