<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Contracts;

interface VectorizerInterface
{
    /**
     * @param array<int, string> $documents
     */
    public function fit(array $documents): void;

    /**
     * @param array<int, string> $documents
     * @return array<int, array<int, float>>
     */
    public function transform(array $documents): array;
}
