<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\VectorStore;

use ML\IDEA\Exceptions\SerializationException;
use ML\IDEA\RAG\Contracts\VectorStoreInterface;

final class JsonFileVectorStore implements VectorStoreInterface
{
    public function __construct(private readonly string $path)
    {
    }

    public function upsert(array $items): void
    {
        $current = $this->loadAll();

        foreach ($items as $item) {
            $current[$item['id']] = [
                'id' => $item['id'],
                'vector' => $item['vector'],
                'text' => $item['text'],
                'metadata' => $item['metadata'] ?? [],
            ];
        }

        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $json = json_encode(array_values($current), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        if (file_put_contents($this->path, $json) === false) {
            throw new SerializationException(sprintf('Failed to write vector store file: %s', $this->path));
        }
    }

    public function search(array $queryVector, int $k = 5, array $filters = []): array
    {
        $scored = [];
        foreach ($this->loadAll() as $item) {
            $meta = $item['metadata'];
            if (!$this->matchesFilters($meta, $filters)) {
                continue;
            }

            $scored[] = [
                'id' => $item['id'],
                'vector' => $item['vector'],
                'text' => $item['text'],
                'metadata' => $meta,
                'score' => self::cosineSimilarity($queryVector, $item['vector']),
            ];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, max(1, $k));
    }

    /** @return array<string, array{id: string, vector: array<int, float>, text: string, metadata: array<string, mixed>}> */
    private function loadAll(): array
    {
        if (!is_file($this->path)) {
            return [];
        }

        $raw = file_get_contents($this->path);
        if ($raw === false) {
            throw new SerializationException(sprintf('Failed to read vector store file: %s', $this->path));
        }

        /** @var array<int, array{id: string, vector: array<int, float>, text: string, metadata?: array<string, mixed>}> $list */
        $list = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        $indexed = [];
        foreach ($list as $item) {
            $indexed[$item['id']] = [
                'id' => $item['id'],
                'vector' => array_map(static fn ($v): float => (float) $v, $item['vector']),
                'text' => $item['text'],
                'metadata' => $item['metadata'] ?? [],
            ];
        }

        return $indexed;
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
