<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\NLP\Pos\RuleBasedPosTagger;
use ML\IDEA\NLP\Privacy\PIIRedactor;
use ML\IDEA\NLP\Privacy\SensitiveTermFilter;
use ML\IDEA\NLP\Text\Text;
use ML\IDEA\NLP\Tokenize\SentenceTokenizer;
use ML\IDEA\NLP\Tokenize\UnicodeWordTokenizer;
use PHPUnit\Framework\TestCase;

final class NlpTextApiTest extends TestCase
{
    public function testFluentTextApiSupportsNormalizationAndTokens(): void
    {
        $text = Text::of("  Héllo,   WORLD! 😀  ")
            ->normalizeUnicode('NFC')
            ->stripAccents()
            ->lower()
            ->removeEmoji()
            ->removePunctuation()
            ->collapseWhitespace();

        self::assertSame('hello world', $text->value());
        self::assertSame(['hello', 'world'], $text->words());
        self::assertSame(['hello world'], $text->ngrams(2));
    }

    public function testSentenceTokenizerHandlesAbbreviationsReasonably(): void
    {
        $split = (new SentenceTokenizer())->split('Dr. Smith arrived. He left at 5 p.m. sharp.');
        self::assertNotEmpty($split);
        self::assertStringContainsString('Dr. Smith', $split[0]);
    }

    public function testUnicodeTokenizerProvidesOffsets(): void
    {
        $tokens = (new UnicodeWordTokenizer())->tokenize('Hello Zürich');
        self::assertCount(2, $tokens);
        self::assertSame('Hello', $tokens[0]->text);
        self::assertGreaterThan($tokens[0]->start, $tokens[0]->end);
    }

    public function testPiiRedactorMasksSensitivePatterns(): void
    {
        $redacted = (new PIIRedactor())->redact('Email me at a@b.com or visit https://example.com');
        self::assertStringContainsString('[EMAIL]', $redacted);
        self::assertStringContainsString('[URL]', $redacted);
    }

    public function testSensitiveTermFilterFindsAndRedacts(): void
    {
        $filter = new SensitiveTermFilter(['secret', 'internal'], true);
        $found = $filter->find('This is secrat and INTERNAL note');
        self::assertContains('secret', $found);
        self::assertContains('internal', $found);

        $redacted = $filter->redact('internal secret');
        self::assertStringContainsString('[SENSITIVE]', $redacted);
    }

    public function testRuleBasedPosTaggerAssignsExpectedTags(): void
    {
        $tokens = Text::of('Alice is running quickly')->toTokens();
        $tagged = (new RuleBasedPosTagger())->tag($tokens);

        self::assertSame('PROPN', $tagged[0]['pos']);
        self::assertSame('VERB', $tagged[1]['pos']);
    }
}
