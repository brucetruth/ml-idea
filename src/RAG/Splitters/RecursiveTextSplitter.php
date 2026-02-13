<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Splitters;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\RAG\Contracts\TextSplitterInterface;
use ML\IDEA\RAG\Document;

final class RecursiveTextSplitter implements TextSplitterInterface
{
    public function __construct(
        private readonly int $chunkSize = 800,
        private readonly int $chunkOverlap = 120,
    ) {
        if ($this->chunkSize <= 0 || $this->chunkOverlap < 0 || $this->chunkOverlap >= $this->chunkSize) {
            throw new InvalidArgumentException('Invalid chunkSize/chunkOverlap settings.');
        }
    }

    public function splitDocuments(array $documents): array
    {
        $chunks = [];
        foreach ($documents as $doc) {
            $text = trim($doc->text);
            if ($text == '') {
                continue;
            }

            $offset = 0;
            $index = 0;
            $len = strlen($text);
            while ($offset < $len) {
                $piece = substr($text, $offset, $this->chunkSize);
                $chunks[] = [
                    'id' => $doc->id . '#chunk-' . $index,
                    'text' => $piece,
                    'metadata' => array_merge($doc->metadata, ['document_id' => $doc->id, 'chunk_index' => $index]),
                ];

                $step = $this->chunkSize - $this->chunkOverlap;
                $offset += $step;
                $index++;
            }
        }

        return $chunks;
    }
}
