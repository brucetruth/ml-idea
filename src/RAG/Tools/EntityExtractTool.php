<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Tools;

use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Explorer\DocumentExplorerService;

final class EntityExtractTool implements ToolInterface
{
    public function __construct(private readonly DocumentExplorerService $service)
    {
    }

    public function name(): string { return 'entity_extract'; }
    public function description(): string { return 'Extract entities from corpus, it is limited with entity types. Input: {"types":["COUNTRY","CITY"]}'; }

    public function invoke(array $input): string
    {
        $types = isset($input['types']) && is_array($input['types']) ? array_map(static fn ($v): string => (string) $v, $input['types']) : [];
        return json_encode([
            'ok' => true,
            'types' => $types,
            'entities' => $this->service->entityExtract($types),
        ], JSON_THROW_ON_ERROR);
    }
}
