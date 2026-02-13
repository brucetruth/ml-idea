<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Pos;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\NLP\Contracts\TrainablePosTaggerInterface;
use ML\IDEA\NLP\Text\Token;

final class PerceptronPosTagger implements TrainablePosTaggerInterface
{
    /** @var array<string, array<string, float>> */
    private array $weights = [];
    /** @var array<int, string> */
    private array $labels = [];

    /**
     * @param array<int, array<int, string>> $sentences
     * @param array<int, array<int, string>> $labels
     */
    public function train(array $sentences, array $labels, int $epochs = 5): void
    {
        if (count($sentences) !== count($labels)) {
            throw new InvalidArgumentException('Training sentences and labels size mismatch.');
        }

        $labelSet = [];
        foreach ($labels as $seq) {
            foreach ($seq as $tag) {
                $labelSet[$tag] = true;
            }
        }
        $this->labels = array_keys($labelSet);

        for ($e = 0; $e < max(1, $epochs); $e++) {
            foreach ($sentences as $i => $words) {
                $gold = $labels[$i];
                if (count($words) !== count($gold)) {
                    continue;
                }

                $pred = $this->predictWords($words);
                foreach ($words as $j => $word) {
                    if ($pred[$j] === $gold[$j]) {
                        continue;
                    }

                    $features = $this->features($words, $j, $j > 0 ? $pred[$j - 1] : '<S>');
                    foreach ($features as $f) {
                        $this->weights[$f][$gold[$j]] = ($this->weights[$f][$gold[$j]] ?? 0.0) + 1.0;
                        $this->weights[$f][$pred[$j]] = ($this->weights[$f][$pred[$j]] ?? 0.0) - 1.0;
                    }
                }
            }
        }
    }

    /**
     * @param array<int, Token> $tokens
     * @return array<int, array{token: Token, pos: string}>
     */
    public function tag(array $tokens): array
    {
        $words = array_map(static fn (Token $t): string => $t->text, $tokens);
        $pred = $this->predictWords($words);
        $out = [];
        foreach ($tokens as $i => $token) {
            $out[] = ['token' => $token, 'pos' => $pred[$i] ?? 'NOUN'];
        }
        return $out;
    }

    /** @param array<int, string> $words @return array<int, string> */
    private function predictWords(array $words): array
    {
        $out = [];
        foreach ($words as $i => $w) {
            $prev = $i > 0 ? $out[$i - 1] : '<S>';
            $features = $this->features($words, $i, $prev);
            $scores = array_fill_keys($this->labels === [] ? ['NOUN'] : $this->labels, 0.0);

            foreach ($features as $f) {
                foreach (($this->weights[$f] ?? []) as $label => $weight) {
                    $scores[$label] = ($scores[$label] ?? 0.0) + $weight;
                }
            }

            arsort($scores);
            $out[] = (string) array_key_first($scores);
        }
        return $out;
    }

    /** @param array<int, string> $words @return array<int, string> */
    private function features(array $words, int $i, string $prevTag): array
    {
        $w = $words[$i] ?? '';
        $n = mb_strtolower($w);
        $prev = mb_strtolower($words[$i - 1] ?? '<S>');
        $next = mb_strtolower($words[$i + 1] ?? '</S>');

        return [
            'bias',
            'w=' . $n,
            'p1=' . mb_substr($n, 0, 1),
            's2=' . mb_substr($n, -2),
            's3=' . mb_substr($n, -3),
            'prev=' . $prev,
            'next=' . $next,
            'prevTag=' . $prevTag,
            'isUpper=' . ((mb_strtoupper($w) === $w && mb_strtolower($w) !== $w) ? '1' : '0'),
            'isDigit=' . (preg_match('/^\d+$/', $n) === 1 ? '1' : '0'),
        ];
    }
}
