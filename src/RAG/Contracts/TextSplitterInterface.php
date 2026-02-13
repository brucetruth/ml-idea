<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Contracts;

use ML\IDEA\RAG\Document;

interface TextSplitterInterface
{
    /**
     * @param array<int, Document> $documents
     * @return array<int, array{id: string, text: string, metadata: array<string, mixed>}>
     */
    public function splitDocuments(array $documents): array;
}
