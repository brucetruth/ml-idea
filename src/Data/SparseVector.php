<?php

declare(strict_types=1);

namespace ML\IDEA\Data;

/** Column-indexed sparse vector utilities (CSR row: index => value). */
final class SparseVector
{
    /** @param array<int, float|int> $dense @return array<int, float> non-zero columns */
    public static function fromDense(array $dense): array
    {
        $sparse = [];
        foreach ($dense as $index => $value) {
            $v = (float) $value;
            if ($v !== 0.0) {
                $sparse[(int) $index] = $v;
            }
        }

        return $sparse;
    }

    /**
     * @param array<int, float> $sparse
     * @return array<int, float>
     */
    public static function toDense(array $sparse, int $dimensions): array
    {
        $dense = array_fill(0, $dimensions, 0.0);
        foreach ($sparse as $index => $value) {
            if ($index >= 0 && $index < $dimensions) {
                $dense[$index] = (float) $value;
            }
        }

        return $dense;
    }

    /** @param array<int, float> $a @param array<int, float> $b */
    public static function dot(array $a, array $b): float
    {
        if (count($a) > count($b)) {
            [$a, $b] = [$b, $a];
        }

        $sum = 0.0;
        foreach ($a as $index => $value) {
            if (isset($b[$index])) {
                $sum += $value * $b[$index];
            }
        }

        return $sum;
    }

    /**
     * Cosine similarity for dense or sparse vectors (index => value).
     *
     * @param array<int, float|int> $a
     * @param array<int, float|int> $b
     */
    public static function cosineSimilarity(array $a, array $b, ?int $dimensions = null): float
    {
        if (self::isSparseRow($a, $dimensions) || self::isSparseRow($b, $dimensions)) {
            $dims = $dimensions ?? max(
                self::isSparseRow($a) ? (empty($a) ? 0 : max(array_keys($a)) + 1) : count($a),
                self::isSparseRow($b) ? (empty($b) ? 0 : max(array_keys($b)) + 1) : count($b),
            );
            $a = self::isSparseRow($a, $dims) ? self::toDense($a, $dims) : $a;
            $b = self::isSparseRow($b, $dims) ? self::toDense($b, $dims) : $b;
        }

        $n = min(count($a), count($b));
        if ($n === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $va = (float) $a[$i];
            $vb = (float) $b[$i];
            $dot += $va * $vb;
            $normA += $va * $va;
            $normB += $vb * $vb;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    /**
     * Densify sparse rows for algorithms expecting contiguous feature vectors.
     *
     * @param array<int, array<int, float|int>> $samples
     * @return array<int, array<int, float>>
     */
    public static function prepareDenseMatrix(array $samples, ?int $dimensions = null): array
    {
        if ($samples === [] || !self::isSparseRow($samples[0], $dimensions)) {
            return $samples;
        }

        $dims = $dimensions;
        if ($dims === null) {
            foreach ($samples as $row) {
                if ($row !== []) {
                    $dims = max($dims ?? 0, max(array_keys($row)) + 1);
                }
            }
            $dims ??= 0;
        }

        return self::densifyMatrix($samples, $dims);
    }

    /**
     * @param array<int, array<int, float>> $matrix
     * @return array<int, array<int, float>>
     */
    public static function densifyMatrix(array $matrix, ?int $dimensions = null): array
    {
        if ($matrix === []) {
            return [];
        }

        if (!self::isSparseRow($matrix[0], $dimensions)) {
            return $matrix;
        }

        $dims = $dimensions ?? (max(array_keys($matrix[0])) + 1);
        $dense = [];
        foreach ($matrix as $row) {
            $dense[] = self::toDense($row, $dims);
        }

        return $dense;
    }

    /** @param array<int, float|int>|array<int, float> $row */
    public static function isSparseRow(array $row, ?int $dimensions = null): bool
    {
        if ($row === []) {
            return false;
        }

        $keys = array_keys($row);
        if (!is_int($keys[0])) {
            return false;
        }

        if ($dimensions !== null && count($row) < $dimensions) {
            return true;
        }

        $expected = range(0, count($row) - 1);

        return $keys !== $expected;
    }
}
