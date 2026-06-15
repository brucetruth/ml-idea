<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\QueryExpansion;

use ML\IDEA\NLP\Extract\Stopwords;
use ML\IDEA\NLP\Lexicon\WordNetLexicon;
use ML\IDEA\NLP\Lexicon\WordNetQueryExpander as NlpWordNetQueryExpander;
use ML\IDEA\RAG\Contracts\QueryExpanderInterface;

final class WordNetQueryExpander implements QueryExpanderInterface
{
    public function __construct(
        private readonly NlpWordNetQueryExpander $expander = new NlpWordNetQueryExpander(),
        private readonly int $maxQueries = 5,
        private readonly bool $includeSimpleFallback = true,
        private readonly string $language = 'en',
    ) {
    }

    public static function fromLexicon(WordNetLexicon $lexicon, int $maxQueries = 5): self
    {
        return new self(new NlpWordNetQueryExpander($lexicon), $maxQueries);
    }

    public function expand(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $queries = $this->expander->expand($query);

        if ($this->includeSimpleFallback) {
            $queries[] = $query . ' explanation';
            $queries[] = 'about ' . $query;
        }

        $filtered = [];
        foreach ($queries as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '' || !$this->isRetrievalQuery($candidate, $query)) {
                continue;
            }
            if (!in_array($candidate, $filtered, true)) {
                $filtered[] = $candidate;
            }
        }

        if ($filtered === []) {
            return [$query];
        }

        return array_slice($filtered, 0, max(1, $this->maxQueries));
    }

    private function isRetrievalQuery(string $candidate, string $original): bool
    {
        if ($candidate === $original) {
            return true;
        }

        if (str_starts_with(mb_strtolower($candidate), 'about ')
            || str_ends_with(mb_strtolower($candidate), ' explanation')) {
            return mb_strlen($candidate) >= 8;
        }

        $tokens = preg_split('/\s+/u', mb_strtolower($candidate)) ?: [];
        $content = Stopwords::filter($tokens, $this->language);

        return count($tokens) >= 2 && count($content) >= 1;
    }
}
