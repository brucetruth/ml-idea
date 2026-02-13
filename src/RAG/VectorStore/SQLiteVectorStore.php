<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\VectorStore;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\RAG\Contracts\VectorStoreInterface;

final class SQLiteVectorStore implements VectorStoreInterface
{
    private \SQLite3 $db;

    public function __construct(private readonly string $path)
    {
        if (!class_exists(\SQLite3::class)) {
            throw new InvalidArgumentException('SQLite3 extension is required for SQLiteVectorStore.');
        }

        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $this->db = new \SQLite3($this->path);
        $this->db->exec('CREATE TABLE IF NOT EXISTS vectors (id TEXT PRIMARY KEY, vector_json TEXT NOT NULL, text_value TEXT NOT NULL, metadata_json TEXT NOT NULL)');
    }

    public function upsert(array $items): void
    {
        $stmt = $this->db->prepare('INSERT INTO vectors(id, vector_json, text_value, metadata_json) VALUES (:id,:vector_json,:text_value,:metadata_json) ON CONFLICT(id) DO UPDATE SET vector_json = excluded.vector_json, text_value = excluded.text_value, metadata_json = excluded.metadata_json');
        if ($stmt === false) {
            throw new InvalidArgumentException('Failed to prepare SQLite statement for upsert.');
        }

        foreach ($items as $item) {
            $stmt->bindValue(':id', $item['id'], SQLITE3_TEXT);
            $stmt->bindValue(':vector_json', json_encode($item['vector'], JSON_THROW_ON_ERROR), SQLITE3_TEXT);
            $stmt->bindValue(':text_value', $item['text'], SQLITE3_TEXT);
            $stmt->bindValue(':metadata_json', json_encode($item['metadata'] ?? [], JSON_THROW_ON_ERROR), SQLITE3_TEXT);
            $stmt->execute();
            $stmt->reset();
            $stmt->clear();
        }
    }

    public function search(array $queryVector, int $k = 5, array $filters = []): array
    {
        $result = $this->db->query('SELECT id, vector_json, text_value, metadata_json FROM vectors');
        if ($result === false) {
            return [];
        }

        $scored = [];
        while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
            /** @var array<int, float> $vector */
            $vector = array_map(static fn ($v): float => (float) $v, json_decode((string) $row['vector_json'], true, 512, JSON_THROW_ON_ERROR));
            /** @var array<string, mixed> $metadata */
            $metadata = json_decode((string) $row['metadata_json'], true, 512, JSON_THROW_ON_ERROR);

            if (!$this->matchesFilters($metadata, $filters)) {
                continue;
            }

            $scored[] = [
                'id' => (string) $row['id'],
                'vector' => $vector,
                'text' => (string) $row['text_value'],
                'metadata' => $metadata,
                'score' => self::cosineSimilarity($queryVector, $vector),
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
