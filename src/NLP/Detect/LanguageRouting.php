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
        $lang = trim(mb_strtolower($language));

        return match ($lang) {
            'bem' => [
                'tokenizer' => 'unicode_word',
                'nerPreset' => 'zambia-bemba',
                'translatorDirection' => 'bem->en',
            ],
            'nya' => [
                'tokenizer' => 'unicode_word',
                'nerPreset' => 'zambia-nyanja',
                'translatorDirection' => 'nya->en',
            ],
            'toi' => [
                'tokenizer' => 'unicode_word',
                'nerPreset' => 'zambia-tonga',
                'translatorDirection' => 'toi->en',
            ],
            'loz' => [
                'tokenizer' => 'unicode_word',
                'nerPreset' => 'zambia-lozi',
                'translatorDirection' => 'loz->en',
            ],
            default => [
                'tokenizer' => 'unicode_word',
                'nerPreset' => 'default',
                'translatorDirection' => 'en->bem',
            ],
        };
    }
}
