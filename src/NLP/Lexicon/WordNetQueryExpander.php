<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Lexicon;

final class WordNetQueryExpander
{
    public function __construct(private readonly WordNetLexicon $lexicon = new WordNetLexicon())
    {
    }

    /** @return array<int, string> */
    public function expand(string $query, int $synonymsPerTerm = 3): array
    {
        $parts = preg_split('/\s+/u', trim(mb_strtolower($query))) ?: [];
        return $this->lexicon->expandTerms(array_values(array_filter($parts, static fn ($v) => $v !== '')), $synonymsPerTerm);
    }
}
