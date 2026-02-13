<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Translate;

use ML\IDEA\NLP\Contracts\TranslatorInterface;

final class HybridTranslator implements TranslatorInterface
{
    /** @var (callable(string):string)|null */
    private $transliterator;

    /**
     * @param array<string, string> $reorderRules regex=>replacement
     * @param (callable(string):string)|null $transliterator
     */
    public function __construct(
        private readonly PhraseTableTranslator $phraseTranslator,
        private readonly DictionaryTranslator $dictionaryTranslator,
        private readonly array $reorderRules = [],
        ?callable $transliterator = null,
    ) {
        $this->transliterator = $transliterator;
    }

    public function translate(string $text, ?string $sourceLang = null, ?string $targetLang = null): string
    {
        // 1) phrase table (2-5 grams, longest-first)
        $out = $this->phraseTranslator->translate($text, $sourceLang, $targetLang);

        // 2) word dictionary fallback for remaining tokens
        $out = $this->dictionaryTranslator->translate($out, $sourceLang, $targetLang);

        // 3) optional minimal reorder rules
        foreach ($this->reorderRules as $pattern => $replacement) {
            $out = (string) preg_replace($pattern, $replacement, $out);
        }

        // 4) optional transliteration pass
        if ($this->transliterator !== null) {
            $out = (string) ($this->transliterator)($out);
        }

        return $out;
    }
}
