<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

use ML\IDEA\RAG\Contracts\EpisodicMemorySummarizerInterface;
use ML\IDEA\RAG\Contracts\LlmClientInterface;

final class LlmEpisodicMemorySummarizer implements EpisodicMemorySummarizerInterface
{
    public function __construct(
        private readonly LlmClientInterface $llm,
        private readonly EpisodicMemorySummarizerInterface $fallback = new TruncatingEpisodicMemorySummarizer(),
    ) {
    }

    public function summarize(array $episodes, int $maxChars = 1200): string
    {
        if ($episodes === []) {
            return '';
        }

        $bulletList = $this->fallback->summarize($episodes, max($maxChars * 2, 2400));
        if ($bulletList === '') {
            return '';
        }

        $prompt = implode("\n", [
            'Summarize these agent session episodes for routing context.',
            'Keep facts: tool names, outcomes, user/order ids mentioned.',
            'Max ' . $maxChars . ' characters. No markdown.',
            '',
            $bulletList,
        ]);

        try {
            $summary = trim($this->llm->generate($prompt, ['prefix' => 'MEMORY']));
        } catch (\Throwable) {
            return $this->fallback->summarize($episodes, $maxChars);
        }

        if ($summary === '') {
            return $this->fallback->summarize($episodes, $maxChars);
        }

        if (strlen($summary) > $maxChars) {
            return substr($summary, 0, $maxChars - 3) . '...';
        }

        return $summary;
    }
}
