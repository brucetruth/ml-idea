<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Pipeline;

use ML\IDEA\NLP\Contracts\PipelineComponentInterface;
use ML\IDEA\NLP\Doc\Doc;
use ML\IDEA\NLP\Tokenize\SentenceTokenizer;

final class SentsComponent implements PipelineComponentInterface
{
    public function name(): string
    {
        return 'sents';
    }

    public function process(Doc $doc, string $text): Doc
    {
        return new Doc(
            text: $doc->text,
            tokens: $doc->tokens,
            ents: $doc->ents,
            sents: (new SentenceTokenizer())->split($text),
            language: $doc->language,
            languageScores: $doc->languageScores,
            languageSegments: $doc->languageSegments,
            attrs: $doc->attrs,
        );
    }
}
