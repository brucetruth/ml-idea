<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Contracts;

interface EmbeddingProviderInterface
{
    /**
     * @return array<int, float>
     */
    public function embed(string $text): array;
}
