<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Contracts;

interface EpisodicMemorySummarizerInterface
{
    /**
     * @param array<int, array<string, mixed>> $episodes
     */
    public function summarize(array $episodes, int $maxChars = 1200): string;
}
