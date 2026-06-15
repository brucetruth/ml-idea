<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\NLP\Lexicon\WordNetLexicon;
use ML\IDEA\NLP\Lexicon\WordNetQueryExpander as NlpWordNetQueryExpander;
use ML\IDEA\RAG\QueryExpansion\WordNetQueryExpander;
use PHPUnit\Framework\TestCase;

final class WordNetQueryExpanderTest extends TestCase
{
    private string $seedPath;

    protected function setUp(): void
    {
        $this->seedPath = dirname(__DIR__) . '/src/datasets/wordnet/wn.json';
    }

    public function testWordNetLexiconLoadsLazilyOnFirstLookup(): void
    {
        $lexicon = new WordNetLexicon($this->seedPath);
        $synonyms = $lexicon->synonyms('happy', 5);

        self::assertNotEmpty($synonyms);
        self::assertNotNull($lexicon->definition('dog'));
    }

    public function testNlpExpanderSkipsInflectionalVariants(): void
    {
        $expander = new NlpWordNetQueryExpander(new WordNetLexicon($this->seedPath));
        $queries = $expander->expand('save and load models', synonymsPerTerm: 5);

        foreach ($queries as $query) {
            self::assertStringNotContainsString('loading', mb_strtolower($query));
        }
    }

    public function testNlpExpanderRewritesContentWordsInsteadOfEmittingStopwords(): void
    {
        $expander = new NlpWordNetQueryExpander(new WordNetLexicon($this->seedPath));
        $queries = $expander->expand('How do I persist models in this library?', synonymsPerTerm: 2);

        self::assertContains('How do I persist models in this library?', $queries);
        foreach ($queries as $query) {
            self::assertFalse(in_array($query, ['how', 'do', 'i', 'in', 'this'], true));
        }
    }

    public function testRagExpanderProducesPhraseQueriesOnly(): void
    {
        $expander = WordNetQueryExpander::fromLexicon(new WordNetLexicon($this->seedPath), maxQueries: 6);
        $queries = $expander->expand('happy dog');

        self::assertContains('happy dog', $queries);
        foreach ($queries as $query) {
            self::assertGreaterThanOrEqual(2, count(preg_split('/\s+/u', trim($query)) ?: []));
        }
        self::assertNotContains('happy', $queries);
        self::assertNotContains('dog', $queries);
    }

    public function testRagExpanderRewritesSynonymPhrases(): void
    {
        $expander = WordNetQueryExpander::fromLexicon(new WordNetLexicon($this->seedPath), maxQueries: 8);
        $queries = $expander->expand('happy dog');

        self::assertContains('happy dog', $queries);
        self::assertTrue(
            count(array_filter(
                $queries,
                static fn (string $q): bool => str_contains(mb_strtolower($q), 'joyful')
                    || str_contains(mb_strtolower($q), 'pleased'),
            )) >= 1,
        );
    }
}
