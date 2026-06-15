<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Lexicon;

use ML\IDEA\NLP\Extract\Stopwords;

final class WordNetQueryExpander
{
    public function __construct(
        private readonly WordNetLexicon $lexicon = new WordNetLexicon(),
        private readonly int $minContentWordLength = 3,
    ) {
    }

    /**
     * Expand a user query into retrieval-friendly phrase variants.
     *
     * Returns the original query plus synonym rewrites (whole-word substitution),
     * skipping stopwords and single-token junk.
     *
     * @return array<int, string>
     */
    public function expand(string $query, int $synonymsPerTerm = 3, string $language = 'en'): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $queries = [$query];
        $parts = preg_split('/\s+/u', mb_strtolower($query)) ?: [];
        $contentWords = array_values(array_filter(
            Stopwords::filter(array_values(array_filter($parts, static fn (string $v): bool => $v !== '')), $language),
            fn (string $word): bool => mb_strlen($word) >= $this->minContentWordLength,
        ));

        foreach ($contentWords as $word) {
            foreach ($this->lexicon->synonyms($word, $synonymsPerTerm, primarySenseOnly: true) as $syn) {
                $synPhrase = trim(str_replace('_', ' ', $syn));
                if (!$this->isUsefulSynonym($synPhrase, $word, $language)) {
                    continue;
                }

                $rewritten = $this->rewriteQuery($query, $word, $synPhrase);
                if ($rewritten !== '' && !in_array($rewritten, $queries, true)) {
                    $queries[] = $rewritten;
                }
            }
        }

        return $queries;
    }

    private function isUsefulSynonym(string $synPhrase, string $sourceWord, string $language): bool
    {
        if ($synPhrase === '' || mb_strlen($synPhrase) < $this->minContentWordLength) {
            return false;
        }

        if (mb_strtolower($synPhrase) === mb_strtolower($sourceWord)) {
            return false;
        }

        if (Stopwords::isStopword($synPhrase, $language)) {
            return false;
        }

        if ($this->looksLikeInflection($synPhrase, $sourceWord)) {
            return false;
        }

        // Prefer multi-word synonyms or phrases; allow single-token synonyms only when long enough.
        if (!str_contains($synPhrase, ' ') && mb_strlen($synPhrase) < 4) {
            return false;
        }

        return true;
    }

    private function rewriteQuery(string $query, string $word, string $replacement): string
    {
        $pattern = '/\b' . preg_quote($word, '/') . '\b/ui';
        $rewritten = preg_replace($pattern, $replacement, $query, 1);

        return is_string($rewritten) ? trim($rewritten) : '';
    }

    private function looksLikeInflection(string $synPhrase, string $sourceWord): bool
    {
        $syn = mb_strtolower($synPhrase);
        $source = mb_strtolower($sourceWord);

        return str_starts_with($syn, $source)
            && mb_strlen($syn) > mb_strlen($source)
            && mb_strlen($syn) - mb_strlen($source) <= 4;
    }
}
