<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

use ML\IDEA\RAG\Contracts\AgentMemoryStoreInterface;
use ML\IDEA\RAG\Contracts\EpisodicMemorySummarizerInterface;

final class FileAgentMemoryStore implements AgentMemoryStoreInterface
{
    public function __construct(
        private readonly string $directory,
        private readonly EpisodicMemorySummarizerInterface $summarizer = new TruncatingEpisodicMemorySummarizer(),
    ) {
    }

    public function append(string $sessionId, array $episode): void
    {
        $path = $this->path($sessionId);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $episodes = $this->readFile($path);
        $episodes[] = [
            ...$episode,
            'at' => $episode['at'] ?? gmdate('c'),
        ];
        file_put_contents($path, json_encode($episodes, JSON_THROW_ON_ERROR), LOCK_EX);
    }

    public function episodes(string $sessionId, int $limit = 20): array
    {
        $episodes = $this->readFile($this->path($sessionId));
        if ($limit <= 0 || count($episodes) <= $limit) {
            return $episodes;
        }

        return array_slice($episodes, -$limit);
    }

    public function summarizeForContext(string $sessionId, int $maxChars = 1200): string
    {
        return $this->summarizer->summarize($this->episodes($sessionId), $maxChars);
    }

    /** @return array<int, array<string, mixed>> */
    private function readFile(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function path(string $sessionId): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $sessionId) ?? 'session';

        return $this->directory . '/' . $safe . '.json';
    }
}
