<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Lexicon;

final class SemanticExplorer
{
    public function __construct(
        private readonly WordNetLexicon $wordNet = new WordNetLexicon(),
        private readonly EnglishDictionaryLexicon $dictionary = new EnglishDictionaryLexicon(),
    ) {
    }

    /**
     * @return array{
     *   word:string,
     *   definition:?string,
     *   synonyms:array<int,string>,
     *   definitionNeighbors:array<int,string>
     * }
     */
    public function wordInsights(string $word, int $synonymsLimit = 15, int $neighborsLimit = 10): array
    {
        $clean = mb_strtolower(trim($word));
        $definition = $this->dictionary->definition($clean) ?? $this->wordNet->definition($clean);
        $synonyms = $this->wordNet->synonyms($clean, $synonymsLimit);
        $neighbors = $definition === null ? [] : $this->dictionary->wordsFromMeaning($definition, $neighborsLimit);

        return [
            'word' => $clean,
            'definition' => $definition,
            'synonyms' => $synonyms,
            'definitionNeighbors' => $neighbors,
        ];
    }

    /** @return array<int, string> */
    public function wordsByMeaning(string $meaningQuery, int $limit = 20): array
    {
        return $this->dictionary->wordsFromMeaning($meaningQuery, $limit);
    }

    /**
     * @return array{
     *   matches:array<int,string>,
     *   expanded:array<int,string>
     * }
     */
    public function semanticSearch(string $input, int $matchLimit = 12, int $synonymsPerWord = 4): array
    {
        $matches = $this->wordsByMeaning($input, $matchLimit);
        $expanded = $this->wordNet->expandTerms($matches, $synonymsPerWord);

        return [
            'matches' => $matches,
            'expanded' => $expanded,
        ];
    }
}
