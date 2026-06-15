<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Extract;

use ML\IDEA\NLP\Detect\LanguageRegistry;

final class Stopwords
{
    /** @var array<string, array<int, string>>|null */
    private static ?array $lists = null;

    /** @return array<int, string> */
    public static function forLanguage(string $language): array
    {
        $code = LanguageRegistry::resolve($language);
        $lists = self::lists();

        return $lists[$code] ?? $lists['en'];
    }

    public static function isStopword(string $word, string $language = 'en'): bool
    {
        $word = mb_strtolower(trim($word));

        return $word !== '' && in_array($word, self::forLanguage($language), true);
    }

    /** @return array<int, string> */
    public static function filter(array $words, string $language = 'en'): array
    {
        $stop = array_flip(self::forLanguage($language));

        return array_values(array_filter(
            $words,
            static fn (string $word): bool => !isset($stop[mb_strtolower(trim($word))]),
        ));
    }

    /** @return array<string, array<int, string>> */
    public static function lists(): array
    {
        if (self::$lists !== null) {
            return self::$lists;
        }

        /** @var array<string, array<int, string>> $lists */
        $lists = require __DIR__ . '/data/language_stopwords.php';
        self::$lists = $lists;

        return self::$lists;
    }

    public static function supportedCount(): int
    {
        return count(self::lists());
    }
}
