<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Pipeline;

use ML\IDEA\NLP\Contracts\PipelineComponentInterface;
use ML\IDEA\NLP\Doc\Doc;
use ML\IDEA\NLP\Doc\DocToken;
use ML\IDEA\NLP\Normalize\EnglishNormalizer;
use ML\IDEA\NLP\Text\NlpPipeline;

final class TaggerComponent implements PipelineComponentInterface
{
    public function __construct(
        private readonly NlpPipeline $pipeline,
        private readonly string $language,
    ) {
    }

    public function name(): string
    {
        return 'tagger';
    }

    public function process(Doc $doc, string $text): Doc
    {
        if ($doc->tokens === []) {
            return $doc;
        }

        $rawTokens = array_map(static fn (DocToken $t) => $t->token, $doc->tokens);
        $tagged = $this->pipeline->posTagger()->tag($rawTokens);
        $docTokens = [];
        foreach ($tagged as $i => $row) {
            $token = $row['token'];
            $lemma = $this->language === 'en'
                ? EnglishNormalizer::normalize($token->norm)
                : $token->norm;
            $docTokens[] = new DocToken($i, $token, $row['pos'] ?? null, $lemma);
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
