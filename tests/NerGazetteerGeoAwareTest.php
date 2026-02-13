<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\NLP\Ner\GazetteerEntityRecognizer;
use ML\IDEA\NLP\Ner\RuleBasedNerTagger;
use ML\IDEA\NLP\Ner\SpanResolver;
use ML\IDEA\NLP\Ner\Entity;
use PHPUnit\Framework\TestCase;

final class NerGazetteerGeoAwareTest extends TestCase
{
    public function testGazetteerRecognizerFindsMultiwordAndAliases(): void
    {
        $gazetteer = [
            'united states' => 'COUNTRY',
            'u.s.' => 'COUNTRY',
            'lusaka' => 'CITY',
        ];

        $r = new GazetteerEntityRecognizer($gazetteer);
        $entities = $r->recognize('Lusaka is not in the U.S.');

        self::assertNotEmpty($entities);
        self::assertContains('CITY', array_map(static fn (Entity $e): string => $e->label, $entities));
    }

    public function testSpanResolverPrefersLongerSpan(): void
    {
        $resolver = new SpanResolver();
        $in = [
            new Entity('United', 'PROPER_NOUN', 0, 6, 0.6),
            new Entity('United States', 'COUNTRY', 0, 13, 0.9),
        ];

        $out = $resolver->resolve($in, allowNesting: false);
        self::assertCount(1, $out);
        self::assertSame('United States', $out[0]->text);
    }

    public function testRuleBasedNerWithAliasesWorks(): void
    {
        $tagger = (new RuleBasedNerTagger())
            ->withAliases(['United States' => ['USA', 'U.S.', 'US']], 'COUNTRY');

        $entities = $tagger->extract('I am in the USA now.');
        $labels = array_map(static fn (Entity $e): string => $e->label, $entities);
        self::assertContains('COUNTRY', $labels);
    }

    public function testRuleBasedNerAutoKnowledgeWorksWithoutManualGazetteer(): void
    {
        $tagger = new RuleBasedNerTagger();
        $entities = $tagger->extract('Lusaka is in Zambia and I visited the U.S.');
        $labels = array_map(static fn (Entity $e): string => $e->label, $entities);

        self::assertContains('CITY', $labels);
        self::assertContains('COUNTRY', $labels);
    }
}
