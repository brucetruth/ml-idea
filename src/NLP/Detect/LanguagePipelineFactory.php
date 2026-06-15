<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Detect;

use ML\IDEA\NLP\Contracts\NerTaggerInterface;
use ML\IDEA\NLP\Contracts\PosTaggerInterface;
use ML\IDEA\NLP\Contracts\TokenizerInterface;
use ML\IDEA\NLP\Contracts\TranslatorInterface;
use ML\IDEA\NLP\Detect\LanguageDetector;
use ML\IDEA\NLP\Ner\RuleBasedNerTagger;
use ML\IDEA\NLP\Pos\RuleBasedPosTagger;
use ML\IDEA\NLP\Sentiment\SentimentAnalyzer;
use ML\IDEA\NLP\Tokenize\UnicodeWordTokenizer;
use ML\IDEA\NLP\Translate\BembaEnglishTranslator;
use ML\IDEA\NLP\Translate\EnglishBembaTranslator;

final class LanguagePipelineFactory
{
    /**
     * Build a language-aware NLP pipeline from routing presets.
     *
     * @return array{
     *   language:string,
     *   routing:array{tokenizer:string,nerPreset:string,translatorDirection:string},
     *   tokenizer:TokenizerInterface,
     *   posTagger:PosTaggerInterface,
     *   nerTagger:NerTaggerInterface,
     *   translator:?TranslatorInterface,
     *   sentiment:SentimentAnalyzer
     * }
     */
    public static function forLanguage(string $language): array
    {
        $lang = trim(mb_strtolower($language));
        $routing = LanguageRouting::forLanguage($lang);

        $tokenizer = self::tokenizer($routing['tokenizer']);
        $posTagger = new RuleBasedPosTagger(self::posLanguage($lang));
        $nerTagger = self::nerTagger($routing['nerPreset']);
        $translator = self::translator($routing['translatorDirection']);

        return [
            'language' => $lang,
            'routing' => $routing,
            'tokenizer' => $tokenizer,
            'posTagger' => $posTagger,
            'nerTagger' => $nerTagger,
            'translator' => $translator,
            'sentiment' => new SentimentAnalyzer(),
        ];
    }

    public static function fromDetectedText(string $text): array
    {
        $language = (new LanguageDetector())->detect($text);

        return self::forLanguage($language);
    }

    private static function tokenizer(string $name): TokenizerInterface
    {
        return match ($name) {
            'unicode_word' => new UnicodeWordTokenizer(),
            default => new UnicodeWordTokenizer(),
        };
    }

    private static function nerTagger(string $preset): NerTaggerInterface
    {
        $tagger = new RuleBasedNerTagger();

        return match ($preset) {
            'zambia-bemba', 'zambia-nyanja', 'zambia-tonga', 'zambia-lozi' => $tagger->withGeoGazetteer(),
            default => $tagger,
        };
    }

    private static function translator(string $direction): ?TranslatorInterface
    {
        return match ($direction) {
            'en->bem' => new EnglishBembaTranslator(),
            'bem->en' => new BembaEnglishTranslator(),
            default => null,
        };
    }

    private static function posLanguage(string $language): string
    {
        return LanguageRegistry::posTaggerLanguage($language);
    }
}
