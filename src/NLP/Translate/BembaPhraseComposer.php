<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Translate;

/**
 * Builds phrase-table entries from a word dictionary and common English templates.
 */
final class BembaPhraseComposer
{
    /** @param array<string, string> $wordMap @param array<int, string> $englishPhrases @return array<string, string> */
    public static function compose(array $wordMap, array $englishPhrases): array
    {
        $out = [];
        foreach ($englishPhrases as $phrase) {
            $norm = mb_strtolower(trim($phrase));
            if ($norm === '' || !str_contains($norm, ' ')) {
                continue;
            }

            $words = preg_split('/\s+/u', $norm) ?: [];
            $parts = [];
            foreach ($words as $word) {
                $translation = $wordMap[mb_strtolower($word)] ?? null;
                if ($translation === null || $translation === '') {
                    $parts = [];
                    break;
                }
                $parts[] = $translation;
            }

            if ($parts !== []) {
                $out[$norm] = implode(' ', $parts);
            }
        }

        return $out;
    }

    /** @return array<int, string> */
    public static function defaultEnglishTemplates(): array
    {
        return [
            'above abdomen',
            'good morning',
            'thank you',
            'how are you',
            'machine learning',
            'model save',
            'load model',
            'save model',
            'add data',
            'learn code',
        ];
    }
}
