<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Contracts;

use ML\IDEA\NLP\Text\Token;

interface PosTaggerInterface
{
    /**
     * @param array<int, Token> $tokens
     * @return array<int, array{token: Token, pos: string}>
     */
    public function tag(array $tokens): array;
}
