<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Pipeline;

use ML\IDEA\NLP\Contracts\PipelineComponentInterface;
use ML\IDEA\NLP\Detect\LanguageDetector;
use ML\IDEA\NLP\Doc\Doc;

final class LanguageDetectComponent implements PipelineComponentInterface
{
    public function __construct(
        private readonly LanguageDetector $detector,
        private readonly string $configuredLanguage,
        private readonly ?string $modelName = null,
    ) {
    }

    public function name(): string
    {
        return 'language';
    }

    public function process(Doc $doc, string $text): Doc
    {
        $detection = $this->detector->detectWithScore($text);
        $segments = $this->detector->detectSegments($text);
        $scores = $this->detector->detectTop($text, 5);
        $scoreMap = [];
        foreach ($scores as $row) {
            $scoreMap[$row['language']] = $row['confidence'];
        }

        $resolved = $this->modelName === 'multilingual'
            ? $detection['language']
            : $this->configuredLanguage;

        return new Doc(
            text: $doc->text,
            tokens: $doc->tokens,
            ents: $doc->ents,
            sents: $doc->sents,
            language: $resolved,
            languageScores: $scoreMap,
            languageSegments: $segments,
            attrs: array_merge($doc->attrs, [
                'confidence' => $detection['confidence'],
                'detected_language' => $detection['language'],
                'configured_language' => $this->configuredLanguage,
            ]),
        );
    }
}
