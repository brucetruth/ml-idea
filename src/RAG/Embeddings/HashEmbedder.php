<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Embeddings;

use ML\IDEA\RAG\Contracts\EmbedderInterface;

/**
 * Deterministic local embedder for tests/examples (no external API calls).
 */
final class HashEmbedder implements EmbedderInterface
{
    public function __construct(private readonly int $dimensions = 32)
    {
    }

    public function embed(string $text): array
    {
        $dims = max(4, $this->dimensions);
        $vec = array_fill(0, $dims, 0.0);

        $tokens = preg_split('/\s+/', strtolower(trim($text))) ?: [];
        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }

            $h = md5($token, true);
            for ($i = 0; $i < $dims; $i++) {
                $byte = ord($h[$i % 16]);
                $vec[$i] += ($byte / 255.0);
            }
        }

        $norm = 0.0;
        foreach ($vec as $v) {
            $norm += $v * $v;
        }
        $norm = sqrt($norm);

        if ($norm > 0.0) {
            foreach ($vec as $i => $v) {
                $vec[$i] = $v / $norm;
            }
        }

        return $vec;
    }

    public function embedBatch(array $texts): array
    {
        return array_map(fn (string $t): array => $this->embed($t), $texts);
    }
}
