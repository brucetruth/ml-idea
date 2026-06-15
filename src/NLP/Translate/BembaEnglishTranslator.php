<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Translate;

use ML\IDEA\Dataset\Services\DictionaryDatasetService;
use ML\IDEA\NLP\Contracts\TranslatorInterface;

/**
 * Bemba -> English dictionary/phrase translator (reverse of bundled EN->BEM lexicon).
 */
final class BembaEnglishTranslator implements TranslatorInterface
{
    private HybridTranslator $pipeline;

    public function __construct(?DictionaryDatasetService $dictionary = null)
    {
        $dictionary ??= new DictionaryDatasetService();
        $word = $dictionary->bembaToEnglishWordMap();
        $phrase = $dictionary->bembaToEnglishPhraseMap(2, 5);

        $this->pipeline = new HybridTranslator(
            new PhraseTableTranslator($phrase, 2, 5),
            new DictionaryTranslator($word),
        );
    }

    public function translate(string $text, ?string $sourceLang = 'bem', ?string $targetLang = 'en'): string
    {
        return $this->pipeline->translate($text, $sourceLang, $targetLang);
    }

    public function translationCoverage(string $text): float
    {
        $draft = $this->translate($text);

        return $this->pipeline->translationCoverage($text, $draft);
    }
}
