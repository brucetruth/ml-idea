<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Index;

use ML\IDEA\Clustering\KMeans;
use ML\IDEA\Exceptions\InvalidArgumentException;

/** IVF-style approximate nearest-neighbor index (k-means inverted lists). */
final class IvfAnnIndex
{
    /** @var array<int, array<int, float>> */
    private array $centroids = [];

    /** @var array<int, array<int, string>> invertedLists listId => item ids */
    private array $invertedLists = [];

    /** @var array<string, array<int, float>> */
    private array $vectors = [];

    private int $dimensions = 0;
    private bool $built = false;

    public function __construct(
        private readonly int $nlist = 8,
        private readonly int $nprobe = 2,
        private readonly ?int $seed = 42,
    ) {
        if ($this->nlist <= 0 || $this->nprobe <= 0) {
            throw new InvalidArgumentException('nlist and nprobe must be positive.');
        }
    }

    /**
     * @param array<int, array{id: string, vector: array<int, float>}> $items
     */
    public function build(array $items): void
    {
        if ($items === []) {
            $this->built = false;
            $this->vectors = [];
            $this->centroids = [];
            $this->invertedLists = [];

            return;
        }

        $this->vectors = [];
        $samples = [];
        foreach ($items as $item) {
            $id = $item['id'];
            $vector = $item['vector'];
            $this->vectors[$id] = $vector;
            $samples[] = $vector;
        }

        $this->dimensions = count($samples[0]);
        $effectiveLists = min($this->nlist, count($samples));

        $kmeans = new KMeans(k: $effectiveLists, maxIterations: 50, seed: $this->seed);
        $kmeans->fit($samples);
        $this->centroids = $kmeans->centroids();

        $this->invertedLists = array_fill(0, $effectiveLists, []);
        $ids = array_keys($this->vectors);
        foreach ($ids as $offset => $id) {
            $listId = $kmeans->predict($samples[$offset]);
            $this->invertedLists[$listId][] = $id;
        }

        $this->built = true;
    }

    /**
     * @param array<int, float> $queryVector
     * @return array<int, array{id: string, score: float}>
     */
    public function search(array $queryVector, int $k = 5): array
    {
        if (!$this->built) {
            throw new InvalidArgumentException('IVF ANN index has not been built.');
        }

        $listScores = [];
        foreach ($this->centroids as $listId => $centroid) {
            $listScores[$listId] = self::cosineSimilarity($queryVector, $centroid);
        }

        arsort($listScores);
        $probeLists = array_slice(array_keys($listScores), 0, min($this->nprobe, count($listScores)));

        $candidates = [];
        foreach ($probeLists as $listId) {
            foreach ($this->invertedLists[$listId] as $id) {
                $candidates[$id] = self::cosineSimilarity($queryVector, $this->vectors[$id]);
            }
        }

        arsort($candidates);
        $results = [];
        foreach (array_slice($candidates, 0, max(1, $k), true) as $id => $score) {
            $results[] = ['id' => $id, 'score' => $score];
        }

        return $results;
    }

    public function isBuilt(): bool
    {
        return $this->built;
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
