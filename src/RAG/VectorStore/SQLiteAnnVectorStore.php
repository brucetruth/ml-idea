<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\VectorStore;

use ML\IDEA\Data\SparseVector;
use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\RAG\Contracts\PersistableVectorStoreInterface;
use ML\IDEA\RAG\Index\IvfAnnIndex;

/** SQLite-backed vector store with in-memory IVF approximate nearest-neighbor search. */
final class SQLiteAnnVectorStore implements PersistableVectorStoreInterface
{
    private \SQLite3 $db;
    private readonly IvfAnnIndex $index;

    /** @var array<string, array{id: string, vector: array<int, float>, text: string, metadata: array<string, mixed>}> */
    private array $items = [];

    public function __construct(
        private readonly string $path,
        private readonly int $nlist = 8,
        private readonly int $nprobe = 2,
        private readonly int $minItemsForAnn = 16,
        private readonly ?int $seed = 42,
    ) {
        if (!class_exists(\SQLite3::class)) {
            throw new InvalidArgumentException('SQLite3 extension is required for SQLiteAnnVectorStore.');
        }

        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $this->db = new \SQLite3($this->path);
        $this->db->exec('CREATE TABLE IF NOT EXISTS vectors (id TEXT PRIMARY KEY, vector_json TEXT NOT NULL, text_value TEXT NOT NULL, metadata_json TEXT NOT NULL)');
        $this->index = new IvfAnnIndex($this->nlist, $this->nprobe, $this->seed);
        $this->loadFromDatabase();
    }

    public function upsert(array $items): void
    {
        $stmt = $this->db->prepare('INSERT INTO vectors(id, vector_json, text_value, metadata_json) VALUES (:id,:vector_json,:text_value,:metadata_json) ON CONFLICT(id) DO UPDATE SET vector_json = excluded.vector_json, text_value = excluded.text_value, metadata_json = excluded.metadata_json');
        if ($stmt === false) {
            throw new InvalidArgumentException('Failed to prepare SQLite statement for upsert.');
        }

        foreach ($items as $item) {
            $id = $item['id'];
            $this->items[$id] = [
                'id' => $id,
                'vector' => $item['vector'],
                'text' => $item['text'],
                'metadata' => $item['metadata'] ?? [],
            ];

            $stmt->bindValue(':id', $id, SQLITE3_TEXT);
            $stmt->bindValue(':vector_json', json_encode($item['vector'], JSON_THROW_ON_ERROR), SQLITE3_TEXT);
            $stmt->bindValue(':text_value', $item['text'], SQLITE3_TEXT);
            $stmt->bindValue(':metadata_json', json_encode($item['metadata'] ?? [], JSON_THROW_ON_ERROR), SQLITE3_TEXT);
            $stmt->execute();
            $stmt->reset();
            $stmt->clear();
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

    private function loadFromDatabase(): void
    {
        $result = $this->db->query('SELECT id, vector_json, text_value, metadata_json FROM vectors');
        if ($result === false) {
            return;
        }

        $this->items = [];
        while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
            $id = (string) $row['id'];
            $this->items[$id] = [
                'id' => $id,
                'vector' => array_map(static fn ($v): float => (float) $v, json_decode((string) $row['vector_json'], true, 512, JSON_THROW_ON_ERROR)),
                'text' => (string) $row['text_value'],
                'metadata' => json_decode((string) $row['metadata_json'], true, 512, JSON_THROW_ON_ERROR) ?: [],
            ];
        }

        $this->rebuildIndex();
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
