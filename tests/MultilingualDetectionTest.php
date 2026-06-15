<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\NLP\Detect\LanguageDetector;
use ML\IDEA\NLP\Text\Text;
use PHPUnit\Framework\TestCase;

final class MultilingualDetectionTest extends TestCase
{
    public function testDetectTopReturnsRankedLanguagesWithConfidence(): void
    {
        $top = (new LanguageDetector())->detectTop('The quick brown fox jumps over the lazy dog.', 3);

        self::assertNotEmpty($top);
        self::assertSame('en', $top[0]['language']);
        self::assertGreaterThan(0.5, $top[0]['confidence']);
    }

    public function testDetectSegmentsMixedDocument(): void
    {
        $text = 'Bonjour le monde. The quick brown fox jumps over the lazy dog.';
        $segments = (new LanguageDetector())->detectSegments($text);

        self::assertCount(2, $segments);
        self::assertSame('fr', $segments[0]['language']);
        self::assertSame('en', $segments[1]['language']);
    }

    public function testDetectMixedFlagsMultilingualDocuments(): void
    {
        $mixed = Text::of('Bonjour le monde. The quick brown fox jumps over the lazy dog.')->languageMixed(0.15);

        self::assertTrue($mixed['multilingual']);
        self::assertContains($mixed['primary'], ['en', 'fr']);
        self::assertGreaterThanOrEqual(2, count($mixed['languages']));
    }

    public function testTextLanguageHelpersExposeTopSegmentsAndMixed(): void
    {
        $text = Text::of('Le chat est noir. The cat is black.');
        self::assertNotEmpty($text->languageTop(2));
        self::assertCount(2, $text->languageSegments());
        self::assertArrayHasKey('multilingual', $text->languageMixed());
        self::assertArrayHasKey('confidence', $text->languageWithScore());
    }

    public function testExpandedProfilesIncludeSwahiliAndArabic(): void
    {
        $profiles = (new LanguageDetector())->profiles();
        self::assertGreaterThanOrEqual(100, count($profiles));
        self::assertArrayHasKey('sw', $profiles);
        self::assertArrayHasKey('ar', $profiles);
        self::assertArrayHasKey('zh', $profiles);
        self::assertArrayHasKey('ja', $profiles);
        self::assertArrayHasKey('tr', $profiles);
        self::assertArrayHasKey('vi', $profiles);
    }
}
