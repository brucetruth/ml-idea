<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Normalize;

final class UnicodeNormalizer
{
    public static function normalize(string $text, string $form = 'NFC'): string
    {
        if (class_exists('\Normalizer')) {
            $const = match ($form) {
                'NFD' => \Normalizer::FORM_D,
                'NFKC' => \Normalizer::FORM_KC,
                'NFKD' => \Normalizer::FORM_KD,
                default => \Normalizer::FORM_C,
            };

            $normalized = \Normalizer::normalize($text, $const);
            if (is_string($normalized)) {
                return $normalized;
            }
        }

        return $text;
    }

    public static function stripAccents(string $text): string
    {
        $normalized = self::normalize($text, 'NFD');
        $withoutMarks = (string) preg_replace('/\p{Mn}+/u', '', $normalized);

        return self::normalize($withoutMarks, 'NFC');
    }
}
