<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Contracts;

interface AgentMemoryStoreInterface
{
    /**
     * @param array<string, mixed> $episode e.g. tool name, outcome, timestamp
     */
    public function append(string $sessionId, array $episode): void;

    /** @return array<int, array<string, mixed>> */
    public function episodes(string $sessionId, int $limit = 20): array;

    /** Short text block injected when routing context is windowed. */
    public function summarizeForContext(string $sessionId, int $maxChars = 1200): string;
}
