<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Models;

use ML\IDEA\NLP\Detect\LanguageRouting;
use ML\IDEA\NLP\Language;
use ML\IDEA\NLP\NlpPipeline;
use ML\IDEA\NLP\Text\NlpPipeline as LegacyPipeline;

/** Named pipeline bundles (spaCy/HF-style model registry). */
final class PipelineRegistry
{
    /** @var array<string, array{language:string, description:string}> */
    private const MODELS = [
        'en' => ['language' => 'en', 'description' => 'English rule POS/NER + sentiment'],
        'en_core' => ['language' => 'en', 'description' => 'Alias for English core pipeline'],
        'fr' => ['language' => 'fr', 'description' => 'French POS heuristics + default NER'],
        'es' => ['language' => 'es', 'description' => 'Spanish POS heuristics + default NER'],
        'multilingual' => ['language' => 'en', 'description' => 'Multilingual detection (100+ languages) with English default processors'],
        'de' => ['language' => 'de', 'description' => 'German POS heuristics + default NER'],
        'de_core' => ['language' => 'de', 'description' => 'Alias for German core pipeline'],
        'pt' => ['language' => 'pt', 'description' => 'Portuguese POS heuristics + default NER'],
        'pt_core' => ['language' => 'pt', 'description' => 'Alias for Portuguese core pipeline'],
        'it' => ['language' => 'it', 'description' => 'Italian POS heuristics + default NER'],
        'ar_core' => ['language' => 'ar', 'description' => 'Arabic pipeline with script-aware detection'],
        'hi_core' => ['language' => 'hi', 'description' => 'Hindi Devanagari pipeline'],
        'ru_core' => ['language' => 'ru', 'description' => 'Russian Cyrillic pipeline'],
        'ja_core' => ['language' => 'ja', 'description' => 'Japanese pipeline with international detection'],
        'ko_core' => ['language' => 'ko', 'description' => 'Korean Hangul pipeline'],
        'zh_core' => ['language' => 'zh', 'description' => 'Chinese Han script pipeline'],
        'sw_core' => ['language' => 'sw', 'description' => 'Swahili East Africa pipeline'],
        'ja' => ['language' => 'ja', 'description' => 'Japanese pipeline with international detection'],
        'zh' => ['language' => 'zh', 'description' => 'Chinese pipeline with international detection'],
        'zambia-bem' => ['language' => 'bem', 'description' => 'Zambia Bemba geo NER + BEM->EN translator'],
        'zambia-nya' => ['language' => 'nya', 'description' => 'Zambia Nyanja geo NER pipeline'],
    ];

    public static function load(string $name): Language
    {
        $config = self::MODELS[$name] ?? self::MODELS['en'];

        return Language::blank($config['language'], $name);
    }

    /** @return array<string, array{language:string, description:string}> */
    public static function models(): array
    {
        return self::MODELS;
    }

    public static function resolveLanguage(string $name): string
    {
        return (self::MODELS[$name] ?? self::MODELS['en'])['language'];
    }

    public static function pipelineFor(string $language): LegacyPipeline
    {
        return LegacyPipeline::forLanguage($language);
    }

    /** @return array{tokenizer:string, nerPreset:string, translatorDirection:string} */
    public static function routingFor(string $language): array
    {
        return LanguageRouting::forLanguage($language);
    }
}
