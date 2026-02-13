<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Extract;

use ML\IDEA\NLP\Tokenize\UnicodeWordTokenizer;

final class RakeKeywords
{
    /** @var array<string, bool> */
    private array $stop;

    /** @param array<int, string> $stopwords */
    public function __construct(array $stopwords = ['the', 'a', 'an', 'and', 'or', 'to', 'of', 'in', 'on', 'for', 'is', 'are', 'was', 'were'])
    {
        $this->stop = array_fill_keys(array_map('mb_strtolower', $stopwords), true);
    }

    /** @return array<int, array{keyword:string, score:float}> */
    public function extract(string $text, int $topK = 10): array
    {
        $tokens = (new UnicodeWordTokenizer())->tokenize($text);
        $phrases = [];
        $current = [];

        foreach ($tokens as $t) {
            $w = $t->norm;
            if (isset($this->stop[$w])) {
                if ($current !== []) {
                    $phrases[] = $current;
                    $current = [];
                }
                continue;
            }
            $current[] = $w;
        }
        if ($current !== []) {
            $phrases[] = $current;
        }

        $freq = [];
        $degree = [];
        foreach ($phrases as $phrase) {
            $len = count($phrase);
            foreach ($phrase as $w) {
                $freq[$w] = ($freq[$w] ?? 0) + 1;
                $degree[$w] = ($degree[$w] ?? 0) + max(0, $len - 1);
            }
        }

        $wordScore = [];
        foreach ($freq as $w => $f) {
            $wordScore[$w] = (($degree[$w] ?? 0) + $f) / $f;
        }

        $out = [];
        foreach ($phrases as $phrase) {
            $k = implode(' ', $phrase);
            $s = 0.0;
            foreach ($phrase as $w) {
                $s += $wordScore[$w] ?? 0.0;
            }
            $out[$k] = max($out[$k] ?? 0.0, $s);
        }

        arsort($out);
        $rows = [];
        foreach (array_slice($out, 0, $topK, true) as $k => $s) {
            $rows[] = ['keyword' => $k, 'score' => $s];
        }

        return $rows;
    }
}
