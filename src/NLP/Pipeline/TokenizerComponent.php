<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Pipeline;

use ML\IDEA\NLP\Contracts\PipelineComponentInterface;
use ML\IDEA\NLP\Doc\Doc;
use ML\IDEA\NLP\Text\NlpPipeline;

final class TokenizerComponent implements PipelineComponentInterface
{
    public function __construct(private readonly NlpPipeline $pipeline)
    {
    }

    public function name(): string
    {
        return 'tokenizer';
    }

    public function process(Doc $doc, string $text): Doc
    {
        $tokens = $this->pipeline->tokenizer()->tokenize($text);
        $docTokens = [];
        foreach ($tokens as $i => $token) {
            $docTokens[] = new \ML\IDEA\NLP\Doc\DocToken($i, $token);
        }

        return new Doc(
            text: $doc->text,
            tokens: $docTokens,
            ents: $doc->ents,
            sents: $doc->sents,
            language: $doc->language,
            languageScores: $doc->languageScores,
            languageSegments: $doc->languageSegments,
            attrs: $doc->attrs,
        );
    }
}
