<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Rerankers;

use ML\IDEA\RAG\Contracts\RerankerInterface;

final class LexicalOverlapReranker implements RerankerInterface
{
    public function __construct(private readonly float $baseScoreWeight = 0.7)
    {
    }

    public function rerank(string $query, array $contexts): array
    {
        $queryTerms = $this->terms($query);

        foreach ($contexts as $i => $ctx) {
            $docTerms = $this->terms($ctx['text']);
            $overlap = $this->jaccard($queryTerms, $docTerms);

            $contexts[$i]['metadata']['diagnostics']['lexical_overlap'] = $overlap;
            $contexts[$i]['score'] = ($this->baseScoreWeight * $ctx['score']) + ((1.0 - $this->baseScoreWeight) * $overlap);
        }

        usort($contexts, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        return $contexts;
    }

    /** @return array<int, string> */
    private function terms(string $text): array
    {
        $parts = preg_split('/[^a-z0-9]+/i', strtolower($text)) ?: [];
        $parts = array_values(array_filter($parts, static fn (string $p): bool => $p !== ''));
        return array_values(array_unique($parts));
    }

    /** @param array<int, string> $a @param array<int, string> $b */
    private function jaccard(array $a, array $b): float
    {
        if ($a === [] && $b === []) {
            return 1.0;
        }

        $ia = array_intersect($a, $b);
        $ua = array_unique(array_merge($a, $b));

        if (count($ua) === 0) {
            return 0.0;
        }

        return count($ia) / count($ua);
    }
}
