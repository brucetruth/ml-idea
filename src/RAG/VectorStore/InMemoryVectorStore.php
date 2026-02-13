<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\VectorStore;

use ML\IDEA\RAG\Contracts\PersistableVectorStoreInterface;

final class InMemoryVectorStore implements PersistableVectorStoreInterface
{
    /** @var array<string, array{id: string, vector: array<int, float>, text: string, metadata: array<string, mixed>}> */
    private array $items = [];

    public function upsert(array $items): void
    {
        foreach ($items as $item) {
            $id = $item['id'];
            $this->items[$id] = [
                'id' => $id,
                'vector' => $item['vector'],
                'text' => $item['text'],
                'metadata' => $item['metadata'] ?? [],
            ];
        }
    }

    public function search(array $queryVector, int $k = 5, array $filters = []): array
    {
        $scored = [];
        foreach ($this->items as $item) {
            if (!$this->matchesFilters($item['metadata'], $filters)) {
                continue;
            }

            $scored[] = [
                'id' => $item['id'],
                'vector' => $item['vector'],
                'text' => $item['text'],
                'metadata' => $item['metadata'],
                'score' => self::cosineSimilarity($queryVector, $item['vector']),
            ];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, max(1, $k));
    }

    public function exportItems(): array
    {
        return array_values($this->items);
    }

    public function importItems(array $items): void
    {
        $this->upsert($items);
    }

    /** @param array<string, mixed> $metadata @param array<string, mixed> $filters */
    private function matchesFilters(array $metadata, array $filters): bool
    {
        foreach ($filters as $key => $value) {
            if (!array_key_exists($key, $metadata) || $metadata[$key] !== $value) {
                return false;
            }
        }

        return true;
    }

    /** @param array<int, float> $a @param array<int, float> $b */
    private static function cosineSimilarity(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        if ($n === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
