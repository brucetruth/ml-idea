<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Retriever;

use ML\IDEA\RAG\Contracts\EmbedderInterface;
use ML\IDEA\RAG\Contracts\RetrieverInterface;
use ML\IDEA\RAG\Contracts\VectorStoreInterface;

final class HybridRetriever implements RetrieverInterface
{
    public function __construct(
        private readonly EmbedderInterface $embedder,
        private readonly VectorStoreInterface $vectorStore,
        private readonly float $denseWeight = 0.7,
    ) {
    }

    public function retrieve(string $query, int $k = 5, array $filters = []): array
    {
        $qv = $this->embedder->embed($query);
        $denseHits = $this->vectorStore->search($qv, max(10, $k * 3), $filters);

        $queryTerms = $this->terms($query);
        foreach ($denseHits as $i => $hit) {
            $lex = $this->bm25Like($queryTerms, $hit['text']);
            $denseHits[$i]['metadata']['diagnostics']['dense_score'] = $hit['score'];
            $denseHits[$i]['metadata']['diagnostics']['lexical_score'] = $lex;
            $denseHits[$i]['score'] = ($this->denseWeight * $hit['score']) + ((1.0 - $this->denseWeight) * $lex);
        }

        usort($denseHits, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        return array_slice($denseHits, 0, max(1, $k));
    }

    /** @return array<int, string> */
    private function terms(string $text): array
    {
        $parts = preg_split('/[^a-z0-9]+/i', strtolower($text)) ?: [];
        return array_values(array_filter($parts, static fn (string $x): bool => $x !== ''));
    }

    /** @param array<int, string> $queryTerms */
    private function bm25Like(array $queryTerms, string $document): float
    {
        $docTerms = $this->terms($document);
        if ($docTerms === []) {
            return 0.0;
        }

        $tf = [];
        foreach ($docTerms as $t) {
            $tf[$t] = ($tf[$t] ?? 0) + 1;
        }

        $score = 0.0;
        $docLen = count($docTerms);
        foreach ($queryTerms as $term) {
            $f = (float) ($tf[$term] ?? 0);
            if ($f <= 0.0) {
                continue;
            }

            // BM25-ish normalization without global corpus stats.
            $k1 = 1.5;
            $b = 0.75;
            $avgdl = 100.0;
            $score += (($f * ($k1 + 1.0)) / ($f + $k1 * (1.0 - $b + $b * ($docLen / $avgdl))));
        }

        return $score;
    }
}
