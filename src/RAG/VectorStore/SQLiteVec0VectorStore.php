<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\VectorStore;

use ML\IDEA\Data\SparseVector;
use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\RAG\Contracts\PersistableVectorStoreInterface;
use ML\IDEA\RAG\Contracts\VectorStoreInterface;

/**
 * sqlite-vec (vec0) vector store when the extension is available; otherwise delegates to SQLiteAnnVectorStore.
 */
final class SQLiteVec0VectorStore implements PersistableVectorStoreInterface
{
    private PersistableVectorStoreInterface $delegate;
    private bool $usesVec0 = false;

    public function __construct(
        private readonly string $path,
        private readonly int $dimensions = 384,
        private readonly int $nlist = 8,
        private readonly int $nprobe = 2,
        private readonly int $minItemsForAnn = 16,
        private readonly ?int $seed = 42,
    ) {
        if ($this->dimensions <= 0) {
            throw new InvalidArgumentException('dimensions must be positive.');
        }

        if (self::isExtensionAvailable($this->dimensions)) {
            $this->delegate = new SQLiteVec0NativeStore($this->path, $this->dimensions);
            $this->usesVec0 = true;
        } else {
            $this->delegate = new SQLiteAnnVectorStore(
                $this->path,
                $this->nlist,
                $this->nprobe,
                $this->minItemsForAnn,
                $this->seed,
            );
        }
    }

    public static function isExtensionAvailable(?int $probeDimensions = 4): bool
    {
        if (!class_exists(\SQLite3::class)) {
            return false;
        }

        try {
            $db = new \SQLite3(':memory:');
            if (method_exists($db, 'enableExtensions') && !@$db->enableExtensions(true)) {
                return false;
            }
            if (!@$db->loadExtension('vec0')) {
                return false;
            }

            $dims = max(1, $probeDimensions ?? 4);
            $db->exec(sprintf('CREATE VIRTUAL TABLE vec_probe USING vec0(embedding float[%d])', $dims));

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function usesVec0(): bool
    {
        return $this->usesVec0;
    }

    public function upsert(array $items): void
    {
        $this->delegate->upsert($items);
    }

    public function search(array $queryVector, int $k = 5, array $filters = []): array
    {
        return $this->delegate->search($queryVector, $k, $filters);
    }

    public function exportItems(): array
    {
        return $this->delegate->exportItems();
    }

    public function importItems(array $items): void
    {
        $this->delegate->importItems($items);
    }
}

/** @internal Native vec0 implementation when sqlite-vec extension is loaded. */
final class SQLiteVec0NativeStore implements PersistableVectorStoreInterface
{
    private \SQLite3 $db;

    /** @var array<string, array{id: string, vector: array<int, float>, text: string, metadata: array<string, mixed>}> */
    private array $items = [];

    public function __construct(
        private readonly string $path,
        private readonly int $dimensions,
    ) {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $this->db = new \SQLite3($this->path);
        if (method_exists($this->db, 'enableExtensions')) {
            @$this->db->enableExtensions(true);
        }
        @$this->db->loadExtension('vec0');

        $this->db->exec('CREATE TABLE IF NOT EXISTS vec_meta (rowid INTEGER PRIMARY KEY, id TEXT UNIQUE NOT NULL, text_value TEXT NOT NULL, metadata_json TEXT NOT NULL)');
        $this->db->exec(sprintf(
            'CREATE VIRTUAL TABLE IF NOT EXISTS vec_embeddings USING vec0(embedding float[%d])',
            $this->dimensions,
        ));

        $this->loadFromDatabase();
    }

    public function upsert(array $items): void
    {
        foreach ($items as $item) {
            $id = (string) $item['id'];
            $vector = $this->normalizeVector($item['vector']);
            $text = (string) $item['text'];
            $metadata = $item['metadata'] ?? [];

            $existing = $this->db->querySingle('SELECT rowid FROM vec_meta WHERE id = ' . $this->db->escapeString($id));
            if ($existing !== null && $existing !== false) {
                $rowid = (int) $existing;
                $this->db->exec('DELETE FROM vec_embeddings WHERE rowid = ' . $rowid);
                $this->db->exec('DELETE FROM vec_meta WHERE rowid = ' . $rowid);
            }

            $stmt = $this->db->prepare('INSERT INTO vec_meta(id, text_value, metadata_json) VALUES (:id, :text, :meta)');
            $stmt->bindValue(':id', $id, SQLITE3_TEXT);
            $stmt->bindValue(':text', $text, SQLITE3_TEXT);
            $stmt->bindValue(':meta', json_encode($metadata, JSON_THROW_ON_ERROR), SQLITE3_TEXT);
            $stmt->execute();
            $rowid = (int) $this->db->lastInsertRowID();

            $blob = pack('f*', ...$vector);
            $insert = $this->db->prepare('INSERT INTO vec_embeddings(rowid, embedding) VALUES (:rowid, :embedding)');
            $insert->bindValue(':rowid', $rowid, SQLITE3_INTEGER);
            $insert->bindValue(':embedding', $blob, SQLITE3_BLOB);
            $insert->execute();

            $this->items[$id] = [
                'id' => $id,
                'vector' => $vector,
                'text' => $text,
                'metadata' => $metadata,
            ];
        }
    }

    public function search(array $queryVector, int $k = 5, array $filters = []): array
    {
        $query = $this->normalizeVector($queryVector);
        $blob = pack('f*', ...$query);

        try {
            $stmt = $this->db->prepare(
                'SELECT m.id, m.text_value, m.metadata_json, e.distance
                 FROM vec_embeddings e
                 JOIN vec_meta m ON m.rowid = e.rowid
                 WHERE e.embedding MATCH :query
                 ORDER BY e.distance
                 LIMIT :k',
            );
            $stmt->bindValue(':query', $blob, SQLITE3_BLOB);
            $stmt->bindValue(':k', max(1, $k * 4), SQLITE3_INTEGER);
            $result = $stmt->execute();
        } catch (\Throwable) {
            return $this->searchExact($query, $k, $filters);
        }

        $scored = [];
        while ($result !== false && ($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
            $id = (string) $row['id'];
            $item = $this->items[$id] ?? null;
            if ($item === null) {
                continue;
            }
            if (!$this->matchesFilters($item['metadata'], $filters)) {
                continue;
            }

            $distance = (float) ($row['distance'] ?? 0.0);
            $scored[] = [
                'id' => $item['id'],
                'vector' => $item['vector'],
                'text' => $item['text'],
                'metadata' => $item['metadata'],
                'score' => 1.0 / (1.0 + $distance),
            ];
        }

        if ($scored === []) {
            return $this->searchExact($query, $k, $filters);
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

    private function loadFromDatabase(): void
    {
        $result = $this->db->query('SELECT rowid, id, text_value, metadata_json FROM vec_meta');
        if ($result === false) {
            return;
        }

        while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
            $id = (string) $row['id'];
            $this->items[$id] = [
                'id' => $id,
                'vector' => array_fill(0, $this->dimensions, 0.0),
                'text' => (string) $row['text_value'],
                'metadata' => json_decode((string) $row['metadata_json'], true, 512, JSON_THROW_ON_ERROR) ?: [],
            ];
        }
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

    /** @param array<int, float|int> $vector @return array<int, float> */
    private function normalizeVector(array $vector): array
    {
        $dense = SparseVector::isSparseRow($vector, $this->dimensions)
            ? SparseVector::toDense($vector, $this->dimensions)
            : $vector;

        if (count($dense) < $this->dimensions) {
            $dense = array_pad(array_map(static fn ($v): float => (float) $v, $dense), $this->dimensions, 0.0);
        } elseif (count($dense) > $this->dimensions) {
            $dense = array_slice($dense, 0, $this->dimensions);
        }

        return array_map(static fn ($v): float => (float) $v, $dense);
    }
}
