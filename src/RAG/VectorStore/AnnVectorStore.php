<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\VectorStore;

use ML\IDEA\Data\SparseVector;
use ML\IDEA\RAG\Contracts\PersistableVectorStoreInterface;
use ML\IDEA\RAG\Index\IvfAnnIndex;

/** Vector store with IVF approximate nearest-neighbor search (exact re-rank on candidates). */
final class AnnVectorStore implements PersistableVectorStoreInterface
{
    private readonly IvfAnnIndex $index;

    /** @var array<string, array{id: string, vector: array<int, float>, text: string, metadata: array<string, mixed>}> */
    private array $items = [];

    public function __construct(
        private readonly int $nlist = 8,
        private readonly int $nprobe = 2,
        private readonly int $minItemsForAnn = 16,
        private readonly ?int $seed = 42,
    ) {
        $this->index = new IvfAnnIndex($this->nlist, $this->nprobe, $this->seed);
    }

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

        $this->rebuildIndex();
    }

    public function search(array $queryVector, int $k = 5, array $filters = []): array
    {
        if (count($this->items) >= $this->minItemsForAnn && $this->index->isBuilt()) {
            return $this->searchAnn($queryVector, $k, $filters);
        }

        return $this->searchExact($queryVector, $k, $filters);
    }

    public function exportItems(): array
    {
        return array_values($this->items);
    }

    public function importItems(array $items): void
    {
        $this->upsert($items);
    }

    /** @param array<string, mixed> $filters */
    private function searchAnn(array $queryVector, int $k, array $filters): array
    {
        $candidateK = min(count($this->items), max($k * 4, $k));
        $hits = $this->index->search($queryVector, $candidateK);
        $scored = [];

        foreach ($hits as $hit) {
            $item = $this->items[$hit['id']] ?? null;
            if ($item === null || !$this->matchesFilters($item['metadata'], $filters)) {
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

    /** @param array<string, mixed> $filters */
    private function searchExact(array $queryVector, int $k, array $filters): array
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

    private function rebuildIndex(): void
    {
        $items = array_map(
            static fn (array $item): array => ['id' => $item['id'], 'vector' => $item['vector']],
            array_values($this->items),
        );
        $this->index->build($items);
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
