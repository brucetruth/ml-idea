<?php

declare(strict_types=1);

namespace ML\IDEA\NLP;

use ML\IDEA\NLP\Detect\LanguageRegistry;
use ML\IDEA\NLP\Models\PipelineRegistry;

/** Primary entrypoint (spaCy `spacy.load` / HF pipeline loader for PHP). */
final class Nlp
{
    public static function load(string $model = 'en'): Language
    {
        return PipelineRegistry::load($model);
    }

    public static function blank(string $language = 'en'): Language
    {
        return Language::blank(LanguageRegistry::resolve($language));
    }

    /** @return array<string, array{language:string, description:string}> */
    public static function models(): array
    {
        return PipelineRegistry::models();
    }

    /** @return array<int, string> ISO 639-1 codes with detection profiles. */
    public static function languages(): array
    {
        return LanguageRegistry::codes();
    }

    /** @return array<string, string> code => English name */
    public static function languageNames(): array
    {
        return LanguageRegistry::listNames();
    }

    public static function languageCount(): int
    {
        return LanguageRegistry::count();
    }

    /** @return array<string, array<int, string>> */
    public static function languagesByFamily(): array
    {
        return LanguageRegistry::byFamily();
    }

    /** @return array<string, array<int, string>> */
    public static function languagesByScript(): array
    {
        return LanguageRegistry::byScript();
    }
}
