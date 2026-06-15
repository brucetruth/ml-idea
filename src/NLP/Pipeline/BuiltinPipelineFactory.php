<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Pipeline;

use ML\IDEA\NLP\Contracts\PipelineComponentInterface;
use ML\IDEA\NLP\Detect\LanguageDetector;
use ML\IDEA\NLP\Normalize\EnglishNormalizer;
use ML\IDEA\NLP\Text\NlpPipeline;
use ML\IDEA\NLP\Tokenize\SentenceTokenizer;

/** Default spaCy-like pipeline components backed by local rule/ML processors. */
final class BuiltinPipelineFactory
{
    /** @return array<string, PipelineComponentInterface> */
    public static function defaultComponents(
        NlpPipeline $pipeline,
        string $language,
        ?LanguageDetector $detector = null,
        ?string $modelName = null,
    ): array {
        return [
            'language' => new LanguageDetectComponent($detector ?? new LanguageDetector(), $language, $modelName),
            'tokenizer' => new TokenizerComponent($pipeline),
            'tagger' => new TaggerComponent($pipeline, $language),
            'ner' => new NerComponent($pipeline),
            'sents' => new SentsComponent(),
        ];
    }

    /** @return list<string> */
    public static function defaultOrder(): array
    {
        return ['language', 'tokenizer', 'tagger', 'ner', 'sents'];
    }
}
