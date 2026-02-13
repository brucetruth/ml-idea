<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Contracts;

use ML\IDEA\RAG\Document;

interface DocumentLoaderInterface
{
    /** @return array<int, Document> */
    public function load(): array;
}
