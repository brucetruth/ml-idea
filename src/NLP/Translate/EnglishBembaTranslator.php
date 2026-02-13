<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Translate;

use ML\IDEA\Dataset\Services\DictionaryDatasetService;
use ML\IDEA\NLP\Contracts\TranslatorInterface;

final class EnglishBembaTranslator implements TranslatorInterface
{
    private HybridTranslator $pipeline;
    private DictionaryTranslator $wordTranslator;

    /** @param array<string, array<int, string>>|null $map */
    public function __construct(?array $map = null, ?DictionaryDatasetService $dictionary = null)
    {
        if ($map !== null) {
            $word = [];
            $phrase = [];
            foreach ($map as $k => $vals) {
                $v = trim((string) ($vals[0] ?? ''));
                if ($v === '') {
                    continue;
                }
                if (str_contains($k, ' ')) {
                    $phrase[$k] = $v;
                } else {
                    $word[$k] = $v;
                }
            }
        } else {
            $dictionary ??= new DictionaryDatasetService();
            $word = $dictionary->englishToBembaWordMap();
            $phrase = $dictionary->englishToBembaPhraseMap(2, 5);
        }

        $this->wordTranslator = new DictionaryTranslator($word);
        $this->pipeline = new HybridTranslator(
            new PhraseTableTranslator($phrase, 2, 5),
            $this->wordTranslator
        );
    }

    public function translateWord(string $english): string
    {
        return $this->wordTranslator->translateWord($english);
    }

    public function translate(string $text, ?string $sourceLang = 'en', ?string $targetLang = 'bem'): string
    {
        return $this->pipeline->translate($text, $sourceLang, $targetLang);
    }
}
