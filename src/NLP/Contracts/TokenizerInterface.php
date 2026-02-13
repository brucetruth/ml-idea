<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Contracts;

use ML\IDEA\NLP\Text\Token;

interface TokenizerInterface
{
    /**
     * @return array<int, Token>
     */
    public function tokenize(string $text): array;
}
