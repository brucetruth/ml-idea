<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Tokenize;

use ML\IDEA\NLP\Contracts\TokenizerInterface;
use ML\IDEA\NLP\Text\Token;

final class WhitespaceTokenizer implements TokenizerInterface
{
    /** @return array<int, Token> */
    public function tokenize(string $text): array
    {
        preg_match_all('/\S+/u', $text, $m, PREG_OFFSET_CAPTURE);

        $tokens = [];
        foreach ($m[0] as $match) {
            /** @var array{0:string,1:int} $match */
            [$raw, $start] = $match;
            $end = $start + strlen($raw);
            $tokens[] = new Token($raw, $start, $end, mb_strtolower($raw), 'term');
        }

        return $tokens;
    }
}
