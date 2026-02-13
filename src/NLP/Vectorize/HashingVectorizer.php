<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Vectorize;

use ML\IDEA\NLP\Contracts\VectorizerInterface;
use ML\IDEA\NLP\Tokenize\UnicodeWordTokenizer;

final class HashingVectorizer implements VectorizerInterface
{
    public function __construct(private readonly int $dimensions = 1024)
    {
    }

    public function fit(array $documents): void
    {
        // Stateless vectorizer.
    }

    /**
     * @param array<int, string> $documents
     * @return array<int, array<int, float>>
     */
    public function transform(array $documents): array
    {
        $tok = new UnicodeWordTokenizer();
        $out = [];

        foreach ($documents as $doc) {
            $row = array_fill(0, $this->dimensions, 0.0);
            foreach ($tok->tokenize($doc) as $t) {
                $h = crc32($t->norm);
                $idx = (int) ($h % $this->dimensions);
                $sign = (($h >> 31) & 1) === 1 ? -1.0 : 1.0;
                $row[$idx] += $sign;
            }

            $norm = 0.0;
            foreach ($row as $v) {
                $norm += $v * $v;
            }
            $norm = sqrt(max(1e-12, $norm));
            foreach ($row as $i => $v) {
                $row[$i] = $v / $norm;
            }

            $out[] = $row;
        }

        return $out;
    }
}
