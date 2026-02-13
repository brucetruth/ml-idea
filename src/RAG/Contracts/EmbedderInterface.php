<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Contracts;

interface EmbedderInterface
{
    /** @return array<int, float> */
    public function embed(string $text): array;

    /**
     * @param array<int, string> $texts
     * @return array<int, array<int, float>>
     */
    public function embedBatch(array $texts): array;
}
