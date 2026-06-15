<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\VectorStore;

use ML\IDEA\Data\SparseVector;
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
                'score' => SparseVector::cosineSimilarity($queryVector, $item['vector']),
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
}
