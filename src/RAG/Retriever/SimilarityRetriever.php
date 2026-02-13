<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Retriever;

use ML\IDEA\RAG\Contracts\EmbedderInterface;
use ML\IDEA\RAG\Contracts\RetrieverInterface;
use ML\IDEA\RAG\Contracts\VectorStoreInterface;

final class SimilarityRetriever implements RetrieverInterface
{
    public function __construct(
        private readonly EmbedderInterface $embedder,
        private readonly VectorStoreInterface $vectorStore,
    ) {
    }

    public function retrieve(string $query, int $k = 5, array $filters = []): array
    {
        $queryVector = $this->embedder->embed($query);
        return $this->vectorStore->search($queryVector, $k, $filters);
    }
}
