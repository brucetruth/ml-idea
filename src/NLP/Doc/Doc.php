<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Doc;

use ML\IDEA\NLP\Ner\Entity;

/** spaCy-style analyzed document. */
final class Doc
{
    /**
     * @param array<int, DocToken> $tokens
     * @param array<int, Entity> $ents
     * @param array<int, string> $sents
     * @param array<string, float> $languageScores
     * @param array<int, array{text:string, start:int, end:int, language:string, score:float, confidence:float}> $languageSegments
     * @param array<string, mixed> $attrs
     */
    public function __construct(
        public readonly string $text,
        public readonly array $tokens = [],
        public readonly array $ents = [],
        public readonly array $sents = [],
        public readonly string $language = 'unknown',
        public readonly array $languageScores = [],
        public readonly array $languageSegments = [],
        public readonly array $attrs = [],
    ) {
    }

    /** @return array<int, string> */
    public function entityTexts(): array
    {
        return array_map(static fn (Entity $entity): string => $entity->text, $this->ents);
    }

    /** @return array<int, array{text:string, label:string, start:int, end:int, confidence:float}> */
    public function spans(?string $label = null): array
    {
        $ents = $this->ents;
        if ($label !== null) {
            $ents = array_values(array_filter(
                $ents,
                static fn (Entity $entity): bool => strcasecmp($entity->label, $label) === 0,
            ));
        }

        return array_map(static fn (Entity $entity): array => $entity->toArray(), $ents);
    }

    public function toJson(int $flags = JSON_THROW_ON_ERROR): string
    {
        return json_encode([
            'text' => $this->text,
            'language' => $this->language,
            'tokens' => array_map(static fn (DocToken $t): array => $t->toArray(), $this->tokens),
            'ents' => $this->spans(),
            'sents' => $this->sents,
            'language_scores' => $this->languageScores,
            'attrs' => $this->attrs,
        ], $flags);
    }

    /** @return array{text:string, language:string, tokens:int, ents:int, sents:int} */
    public function summary(): array
    {
        return [
            'text' => mb_substr($this->text, 0, 120),
            'language' => $this->language,
            'tokens' => count($this->tokens),
            'ents' => count($this->ents),
            'sents' => count($this->sents),
        ];
    }
}
