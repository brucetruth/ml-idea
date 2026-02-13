<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Tokenize;

use ML\IDEA\NLP\Contracts\TokenizerInterface;
use ML\IDEA\NLP\Text\Token;

final class RegexTokenizer implements TokenizerInterface
{
    public function __construct(private readonly string $pattern = '/[\p{L}\p{N}_\-]+/u')
    {
    }

    /** @return array<int, Token> */
    public function tokenize(string $text): array
    {
        preg_match_all($this->pattern, $text, $m, PREG_OFFSET_CAPTURE);
        $tokens = [];

        foreach ($m[0] as $match) {
            /** @var array{0:string,1:int} $match */
            [$raw, $start] = $match;
            $tokens[] = new Token($raw, $start, $start + strlen($raw), mb_strtolower($raw), 'term');
        }

        return $tokens;
    }
}
