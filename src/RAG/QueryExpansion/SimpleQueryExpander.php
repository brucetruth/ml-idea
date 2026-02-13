<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\QueryExpansion;

use ML\IDEA\RAG\Contracts\QueryExpanderInterface;

final class SimpleQueryExpander implements QueryExpanderInterface
{
    public function __construct(private readonly int $maxQueries = 3)
    {
    }

    public function expand(string $query): array
    {
        $expanded = [$query];

        $q = trim($query);
        if ($q !== '') {
            $expanded[] = $q . ' explanation';
            $expanded[] = 'about ' . $q;
        }

        return array_slice(array_values(array_unique($expanded)), 0, max(1, $this->maxQueries));
    }
}
