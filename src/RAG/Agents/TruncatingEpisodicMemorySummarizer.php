<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

use ML\IDEA\RAG\Contracts\EpisodicMemorySummarizerInterface;

final class TruncatingEpisodicMemorySummarizer implements EpisodicMemorySummarizerInterface
{
    public function summarize(array $episodes, int $maxChars = 1200): string
    {
        return EpisodicMemorySummarizer::summarize($episodes, $maxChars);
    }
}
