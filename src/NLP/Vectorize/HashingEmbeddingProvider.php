<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Vectorize;

use ML\IDEA\NLP\Contracts\EmbeddingProviderInterface;
use ML\IDEA\NLP\Contracts\TokenizerInterface;
use ML\IDEA\NLP\Tokenize\UnicodeWordTokenizer;

final class HashingEmbeddingProvider implements EmbeddingProviderInterface
{
    public function __construct(
        private readonly HashingVectorizer $vectorizer = new HashingVectorizer(),
        private readonly TokenizerInterface $tokenizer = new UnicodeWordTokenizer(),
    ) {
    }

    public function embed(string $text): array
    {
        $matrix = $this->vectorizer->transform([$text]);

        return $matrix[0] ?? [];
    }

    public function vectorizer(): HashingVectorizer
    {
        return $this->vectorizer;
    }

    public function tokenizer(): TokenizerInterface
    {
        return $this->tokenizer;
    }
}
