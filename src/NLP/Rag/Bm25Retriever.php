<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Rag;

use ML\IDEA\NLP\Vectorize\BM25;

final class Bm25Retriever
{
    private BM25 $bm25;

    public function __construct(?BM25 $bm25 = null)
    {
        $this->bm25 = $bm25 ?? new BM25();
    }

    /** @param array<int, string> $documents */
    public function index(array $documents): void
    {
        $this->bm25->addDocuments($documents);
        $this->bm25->build();
    }

    /** @return array<int, array{id:int, score:float, text:string}> */
    public function retrieve(string $query, int $topK = 5): array
    {
        return $this->bm25->search($query, $topK);
    }
}
