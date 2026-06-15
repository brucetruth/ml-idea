<?php

declare(strict_types=1);

namespace ML\IDEA\NLP;

use ML\IDEA\Exceptions\ModelNotTrainedException;
use ML\IDEA\Data\SparseVector;
use ML\IDEA\NLP\Contracts\TokenizerInterface;
use ML\IDEA\NLP\Contracts\VectorizerInterface;
use ML\IDEA\NLP\Extract\Stopwords;
use ML\IDEA\NLP\Normalize\EnglishNormalizer;
use ML\IDEA\NLP\Tokenize\UnicodeWordTokenizer;

final class TfidfVectorizer implements VectorizerInterface
{
    /** @var array<string, int> */
    private array $vocabulary = [];

    /** @var array<int, float> */
    private array $idf = [];

    private bool $fitted = false;

    public function __construct(
        private readonly TokenizerInterface $tokenizer = new UnicodeWordTokenizer(),
        private readonly bool $removeStopwords = false,
        private readonly string $stopwordLanguage = 'en',
        private readonly bool $normalizeEnglish = false,
        private readonly bool $outputSparse = false,
    ) {
    }

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
            $tokens = $this->tokenize($document);
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
            $tokens = $this->tokenize($document);
            $termCounts = [];
            foreach ($tokens as $token) {
                if (!isset($this->vocabulary[$token])) {
                    continue;
                }
                $termCounts[$token] = ($termCounts[$token] ?? 0) + 1;
            }

            $totalTerms = max(1, array_sum($termCounts));

            if ($this->outputSparse) {
                $row = [];
                foreach ($termCounts as $term => $count) {
                    $col = $this->vocabulary[$term];
                    $value = ($count / $totalTerms) * $this->idf[$col];
                    if ($value !== 0.0) {
                        $row[$col] = $value;
                    }
                }
                $matrix[] = $row;
                continue;
            }

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

    public function outputsSparse(): bool
    {
        return $this->outputSparse;
    }

    /**
     * @param array<int, array<int, float>> $matrix
     * @return array<int, array<int, float>>
     */
    public function densify(array $matrix): array
    {
        return SparseVector::densifyMatrix($matrix, count($this->vocabulary));
    }

    /** @return array<int, string> */
    private function tokenize(string $text): array
    {
        $tokens = array_map(
            static fn ($token) => $token->norm,
            $this->tokenizer->tokenize(mb_strtolower($text)),
        );

        if (!$this->removeStopwords) {
            return $this->maybeNormalize($tokens);
        }

        return $this->maybeNormalize(Stopwords::filter($tokens, $this->stopwordLanguage));
    }

    /** @param array<int, string> $tokens @return array<int, string> */
    private function maybeNormalize(array $tokens): array
    {
        if (!$this->normalizeEnglish) {
            return $tokens;
        }

        return EnglishNormalizer::normalizeTokens($tokens);
    }
}
