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

    /**
     * Fraction of source-language words that changed during translation (0..1).
     */
    public function translationCoverage(string $source, string $translated): float
    {
        $sourceWords = $this->extractWords($source);
        if ($sourceWords === []) {
            return 0.0;
        }

        $translatedWords = $this->extractWords($translated);
        $changed = 0;
        foreach ($sourceWords as $i => $word) {
            $candidate = $translatedWords[$i] ?? $word;
            if (mb_strtolower($word) !== mb_strtolower($candidate)) {
                $changed++;
            }
        }

        return $changed / count($sourceWords);
    }

    /** @return array<int, string> */
    private function extractWords(string $text): array
    {
        $parts = preg_split('/(\P{L}+)/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(
            $parts,
            static fn (string $part): bool => preg_match('/^\p{L}+$/u', $part) === 1,
        ));
    }
}
