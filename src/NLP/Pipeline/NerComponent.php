<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Pipeline;

use ML\IDEA\NLP\Contracts\PipelineComponentInterface;
use ML\IDEA\NLP\Doc\Doc;
use ML\IDEA\NLP\Text\NlpPipeline;

final class NerComponent implements PipelineComponentInterface
{
    public function __construct(private readonly NlpPipeline $pipeline)
    {
    }

    public function name(): string
    {
        return 'ner';
    }

    public function process(Doc $doc, string $text): Doc
    {
        return new Doc(
            text: $doc->text,
            tokens: $doc->tokens,
            ents: $this->pipeline->nerTagger()->extract($text),
            sents: $doc->sents,
            language: $doc->language,
            languageScores: $doc->languageScores,
            languageSegments: $doc->languageSegments,
            attrs: $doc->attrs,
        );
    }
}
