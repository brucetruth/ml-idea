<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Doc;

use ML\IDEA\NLP\Text\Token;

final readonly class DocToken
{
    public function __construct(
        public int $i,
        public Token $token,
        public ?string $pos = null,
        public ?string $lemma = null,
    ) {
    }

    public function text(): string
    {
        return $this->token->text;
    }

    /** @return array{i:int, text:string, start:int, end:int, pos:?string, lemma:?string} */
    public function toArray(): array
    {
        return [
            'i' => $this->i,
            'text' => $this->token->text,
            'start' => $this->token->start,
            'end' => $this->token->end,
            'pos' => $this->pos,
            'lemma' => $this->lemma,
        ];
    }
}
