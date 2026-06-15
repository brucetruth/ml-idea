<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Rag;

use ML\IDEA\NLP\Contracts\TokenizerInterface;
use ML\IDEA\NLP\Tokenize\UnicodeWordTokenizer;

final class Chunker
{
    public function __construct(private readonly TokenizerInterface $tokenizer = new UnicodeWordTokenizer())
    {
    }

    /**
     * @return array<int, string>
     */
    public function chunkByWords(string $text, int $chunkSize = 120, int $overlap = 20): array
    {
        $words = array_map(
            static fn ($token) => $token->text,
            $this->tokenizer->tokenize(trim($text)),
        );
        if ($words === []) {
            return [];
        }

        $chunkSize = max(1, $chunkSize);
        $overlap = max(0, min($overlap, $chunkSize - 1));
        $step = max(1, $chunkSize - $overlap);

        $chunks = [];
        for ($i = 0; $i < count($words); $i += $step) {
            $slice = array_slice($words, $i, $chunkSize);
            if ($slice === []) {
                break;
            }
            $chunks[] = implode(' ', $slice);
            if (count($slice) < $chunkSize) {
                break;
            }
        }

        return $chunks;
    }
}
