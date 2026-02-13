<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Contracts;

interface QueryExpanderInterface
{
    /** @return array<int, string> */
    public function expand(string $query): array;
}
