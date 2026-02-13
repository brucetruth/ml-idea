<?php

declare(strict_types=1);

namespace ML\IDEA\NLP;

use ML\IDEA\Exceptions\ModelNotTrainedException;

final class TfidfVectorizer
{
    /** @var array<string, int> */
    private array $vocabulary = [];

    /** @var array<int, float> */
    private array $idf = [];

    private bool $fitted = false;

    /**
     * @param array<int, string> $documents
     */
    public function fit(array $documents): void
    {
        if ($documents === []) {
            throw new \InvalidArgumentException('Documents cannot be empty.');
        }

        $docCount = count($documents);
        $documentFrequency = [];

        foreach ($documents as $document) {
            $tokens = self::tokenize($document);
            $seen = [];
            foreach ($tokens as $token) {
                if (isset($seen[$token])) {
                    continue;
                }
                $seen[$token] = true;
                $documentFrequency[$token] = ($documentFrequency[$token] ?? 0) + 1;
            }
        }

        ksort($documentFrequency);
        $this->vocabulary = [];
        $this->idf = [];

        $index = 0;
        foreach ($documentFrequency as $term => $df) {
            $this->vocabulary[$term] = $index;
            $this->idf[$index] = log((1.0 + $docCount) / (1.0 + $df)) + 1.0;
            $index++;
        }

        $this->fitted = true;
    }

    /**
     * @param array<int, string> $documents
     * @return array<int, array<int, float>>
     */
    public function transform(array $documents): array
    {
        if (!$this->fitted) {
            throw new ModelNotTrainedException('TfidfVectorizer must be fitted before transform.');
        }

        $matrix = [];
        $vocabSize = count($this->vocabulary);

        foreach ($documents as $document) {
            $tokens = self::tokenize($document);
            $termCounts = [];
            foreach ($tokens as $token) {
                if (!isset($this->vocabulary[$token])) {
                    continue;
                }
                $termCounts[$token] = ($termCounts[$token] ?? 0) + 1;
            }

            $totalTerms = max(1, array_sum($termCounts));
            $row = array_fill(0, $vocabSize, 0.0);

            foreach ($termCounts as $term => $count) {
                $col = $this->vocabulary[$term];
                $tf = $count / $totalTerms;
                $row[$col] = $tf * $this->idf[$col];
            }

            $matrix[] = $row;
        }

        return $matrix;
    }

    /**
     * @param array<int, string> $documents
     * @return array<int, array<int, float>>
     */
    public function fitTransform(array $documents): array
    {
        $this->fit($documents);
        return $this->transform($documents);
    }

    /** @return array<string, int> */
    public function getVocabulary(): array
    {
        return $this->vocabulary;
    }

    /** @return array<int, string> */
    private static function tokenize(string $text): array
    {
        $normalized = mb_strtolower($text);
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $normalized) ?: [];

        return array_values(array_filter($parts, static fn (string $token): bool => $token !== ''));
    }
}
