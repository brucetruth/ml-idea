<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Loaders;

use ML\IDEA\RAG\Contracts\DocumentLoaderInterface;
use ML\IDEA\RAG\Document;

final class TextArrayLoader implements DocumentLoaderInterface
{
    /**
     * @param array<int, string> $texts
     */
    public function __construct(private readonly array $texts)
    {
    }

    public function load(): array
    {
        $docs = [];
        foreach ($this->texts as $i => $text) {
            $docs[] = new Document('text-' . $i, $text, ['source' => 'array']);
        }

        return $docs;
    }
}
