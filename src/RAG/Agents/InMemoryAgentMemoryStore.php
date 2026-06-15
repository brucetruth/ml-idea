<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

use ML\IDEA\RAG\Contracts\AgentMemoryStoreInterface;
use ML\IDEA\RAG\Contracts\EpisodicMemorySummarizerInterface;

final class InMemoryAgentMemoryStore implements AgentMemoryStoreInterface
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $sessions = [];

    public function __construct(
        private readonly EpisodicMemorySummarizerInterface $summarizer = new TruncatingEpisodicMemorySummarizer(),
    ) {
    }

    public function append(string $sessionId, array $episode): void
    {
        $this->sessions[$sessionId] ??= [];
        $this->sessions[$sessionId][] = [
            ...$episode,
            'at' => $episode['at'] ?? gmdate('c'),
        ];
    }

    public function episodes(string $sessionId, int $limit = 20): array
    {
        $episodes = $this->sessions[$sessionId] ?? [];
        if ($limit <= 0 || count($episodes) <= $limit) {
            return $episodes;
        }

        return array_slice($episodes, -$limit);
    }

    public function summarizeForContext(string $sessionId, int $maxChars = 1200): string
    {
        return $this->summarizer->summarize($this->episodes($sessionId), $maxChars);
    }
}
