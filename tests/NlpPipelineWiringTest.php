<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Dataset\Services\DictionaryDatasetService;
use ML\IDEA\NLP\Detect\LanguagePipelineFactory;
use ML\IDEA\NLP\Detect\LanguageRouting;
use ML\IDEA\NLP\Extract\Stopwords;
use ML\IDEA\NLP\Lexicon\EnglishDictionaryLexicon;
use ML\IDEA\NLP\Lexicon\SemanticExplorer;
use ML\IDEA\NLP\Lexicon\WordNetLexicon;
use ML\IDEA\NLP\Text\NlpPipeline;
use ML\IDEA\NLP\Text\Text;
use ML\IDEA\NLP\Translate\BembaEnglishTranslator;
use ML\IDEA\NLP\Translate\EnglishBembaTranslator;
use PHPUnit\Framework\TestCase;

final class NlpPipelineWiringTest extends TestCase
{
    public function testSupplementalPhrasesLoadFromSeedWhenFullDictionaryTreeExists(): void
    {
        $fullBase = dirname(__DIR__) . '/src/Dataset/dictionary';
        if (!is_dir($fullBase)) {
            self::markTestSkipped('Full dictionary tree not available.');
        }

        $phrases = (new DictionaryDatasetService())->englishToBembaSupplementalPhrases();

        self::assertArrayHasKey('thank you', $phrases);
        self::assertSame('natotela', $phrases['thank you']);
    }

    public function testLanguageRoutingMarksUnsupportedZambiaTranslatorsAsNone(): void
    {
        self::assertSame('none', LanguageRouting::forLanguage('nya')['translatorDirection']);
        self::assertSame('none', LanguageRouting::forLanguage('toi')['translatorDirection']);
        self::assertSame('none', LanguageRouting::forLanguage('loz')['translatorDirection']);
        self::assertSame('bem->en', LanguageRouting::forLanguage('bem')['translatorDirection']);
    }

    public function testLanguagePipelineFactoryProvidesDirectionSpecificTranslators(): void
    {
        $en = LanguagePipelineFactory::forLanguage('en');
        $bem = LanguagePipelineFactory::forLanguage('bem');
        $nya = LanguagePipelineFactory::forLanguage('nya');

        self::assertInstanceOf(EnglishBembaTranslator::class, $en['translator']);
        self::assertInstanceOf(BembaEnglishTranslator::class, $bem['translator']);
        self::assertNull($nya['translator']);
    }

    public function testNlpPipelineLazilyProvidesSemanticExplorer(): void
    {
        $seedWn = dirname(__DIR__) . '/src/datasets/wordnet/wn.json';
        $seedEn = dirname(__DIR__) . '/src/datasets/dictionary/en/en.csv';
        $pipeline = new NlpPipeline(
            semanticExplorer: new SemanticExplorer(new WordNetLexicon($seedWn), new EnglishDictionaryLexicon($seedEn)),
        );

        self::assertInstanceOf(SemanticExplorer::class, $pipeline->semanticExplorer());
        self::assertNotNull($pipeline->semanticExplorer()->wordInsights('dog')['definition']);
    }

    public function testZambiaStopwordListsAreDistinctFromEnglish(): void
    {
        self::assertContains('ndi', Stopwords::forLanguage('nya'));
        self::assertContains('nga', Stopwords::forLanguage('bem'));
        self::assertNotContains('ndi', Stopwords::forLanguage('en'));
    }

    public function testBembaEnglishTranslatorUsesReverseLexicon(): void
    {
        $translator = new BembaEnglishTranslator();

        self::assertSame('abdomen', mb_strtolower($translator->translate('Ifumo')));
        self::assertGreaterThan(0.0, $translator->translationCoverage('Ifumo'));
    }

    public function testTextMaskSensitiveTermsMatchesFilterBehavior(): void
    {
        $masked = Text::of('Keep this secret internal memo safe')
            ->maskSensitiveTerms(['secret', 'internal'])
            ->value();

        self::assertStringContainsString('[SENSITIVE]', $masked);
        self::assertSame(['secret', 'internal'], Text::of('secret internal memo')->findSensitiveTerms(['secret', 'internal']));
    }
}
