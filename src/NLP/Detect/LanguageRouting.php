<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Detect;

final class LanguageRouting
{
    /**
     * @return array{tokenizer:string,nerPreset:string,translatorDirection:string}
     */
    public static function forLanguage(string $language): array
    {
        $lang = LanguageRegistry::resolve(trim(mb_strtolower($language)));

        return match ($lang) {
            'en', 'eng' => [
                'tokenizer' => 'unicode_word',
                'nerPreset' => 'default',
                'translatorDirection' => 'en->bem',
            ],
            'bem' => [
                'tokenizer' => 'unicode_word',
                'nerPreset' => 'zambia-bemba',
                'translatorDirection' => 'bem->en',
            ],
            'nya' => [
                'tokenizer' => 'unicode_word',
                'nerPreset' => 'zambia-nyanja',
                'translatorDirection' => 'none',
            ],
            'toi' => [
                'tokenizer' => 'unicode_word',
                'nerPreset' => 'zambia-tonga',
                'translatorDirection' => 'none',
            ],
            'loz' => [
                'tokenizer' => 'unicode_word',
                'nerPreset' => 'zambia-lozi',
                'translatorDirection' => 'none',
            ],
            default => [
                'tokenizer' => 'unicode_word',
                'nerPreset' => 'default',
                'translatorDirection' => 'none',
            ],
        };
    }
}
