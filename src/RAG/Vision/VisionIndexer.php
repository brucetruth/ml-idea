<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Vision;

use ML\IDEA\RAG\Contracts\VectorStoreInterface;
use ML\IDEA\RAG\Embeddings\VisionPathEmbedder;
use ML\IDEA\Vision\Contracts\VisionEmbedderInterface;

/** Index image files into a vector store for similarity search. */
final class VisionIndexer
{
    /**
     * @param array<string, string> $idToPath map of item id => absolute image path
     * @return array<int, array{id: string, vector: array<int, float>, text: string, metadata: array<string, mixed>}>
     */
    public static function buildItems(VisionEmbedderInterface $embedder, array $idToPath, array $metadataById = []): array
    {
        $rag = new VisionPathEmbedder($embedder);
        $items = [];

        foreach ($idToPath as $id => $path) {
            $items[] = [
                'id' => (string) $id,
                'vector' => $rag->embed($path),
                'text' => basename($path),
                'metadata' => array_merge(['path' => $path], $metadataById[$id] ?? []),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, string> $idToPath
     * @return int number of indexed items
     */
    public static function index(VisionEmbedderInterface $embedder, VectorStoreInterface $store, array $idToPath, array $metadataById = []): int
    {
        $items = self::buildItems($embedder, $idToPath, $metadataById);
        if ($items !== []) {
            $store->upsert($items);
        }

        return count($items);
    }

    /**
     * Scan a directory for image files and index them (id = filename without extension).
     *
     * @param array<int, string> $extensions e.g. ['png', 'jpg', 'jpeg', 'webp']
     * @return int number of indexed items
     */
    public static function indexDirectory(
        VisionEmbedderInterface $embedder,
        VectorStoreInterface $store,
        string $directory,
        array $extensions = ['png', 'jpg', 'jpeg', 'webp', 'gif'],
    ): int {
        if (!is_dir($directory)) {
            return 0;
        }

        $idToPath = [];
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $directory . DIRECTORY_SEPARATOR . $entry;
            if (!is_file($full)) {
                continue;
            }
            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (!in_array($ext, $extensions, true)) {
                continue;
            }
            $idToPath[pathinfo($entry, PATHINFO_FILENAME)] = $full;
        }

        return self::index($embedder, $store, $idToPath);
    }

    /** Search by query image path. @return array<int, array<string, mixed>> */
    public static function searchByImage(
        VisionEmbedderInterface $embedder,
        VectorStoreInterface $store,
        string $queryPath,
        int $k = 5,
        array $filters = [],
    ): array {
        $rag = new VisionPathEmbedder($embedder);

        return $store->search($rag->embed($queryPath), $k, $filters);
    }
}
