<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Ner;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\NLP\Contracts\TrainableNerTaggerInterface;
use ML\IDEA\NLP\Tokenize\UnicodeWordTokenizer;

final class PerceptronNerTagger implements TrainableNerTaggerInterface
{
    /** @var array<string, array<string, float>> */
    private array $weights = [];
    /** @var array<int, string> */
    private array $labels = ['O'];

    /**
     * @param array<int, array<int, string>> $sentences
     * @param array<int, array<int, string>> $bioLabels
     */
    public function train(array $sentences, array $bioLabels, int $epochs = 5): void
    {
        if (count($sentences) !== count($bioLabels)) {
            throw new InvalidArgumentException('Training sentences and BIO labels size mismatch.');
        }

        $set = ['O' => true];
        foreach ($bioLabels as $seq) {
            foreach ($seq as $label) {
                $set[$label] = true;
            }
        }
        $this->labels = array_keys($set);

        for ($e = 0; $e < max(1, $epochs); $e++) {
            foreach ($sentences as $i => $words) {
                $gold = $bioLabels[$i];
                if (count($words) !== count($gold)) {
                    continue;
                }

                $pred = $this->predictLabels($words);
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

    /** @return array<int, Entity> */
    public function extract(string $text): array
    {
        $tokens = (new UnicodeWordTokenizer())->tokenize($text);
        $words = array_map(static fn ($t): string => $t->text, $tokens);
        $labels = $this->predictLabels($words);

        $entities = [];
        $currentLabel = null;
        $currentText = [];
        $start = 0;
        $end = 0;

        foreach ($tokens as $i => $token) {
            $label = $labels[$i] ?? 'O';
            if (str_starts_with($label, 'B-')) {
                if ($currentLabel !== null) {
                    $entities[] = new Entity(implode(' ', $currentText), $currentLabel, $start, $end, 0.72);
                }
                $currentLabel = substr($label, 2);
                $currentText = [$token->text];
                $start = $token->start;
                $end = $token->end;
                continue;
            }

            if (str_starts_with($label, 'I-') && $currentLabel !== null && substr($label, 2) === $currentLabel) {
                $currentText[] = $token->text;
                $end = $token->end;
                continue;
            }

            if ($currentLabel !== null) {
                $entities[] = new Entity(implode(' ', $currentText), $currentLabel, $start, $end, 0.72);
                $currentLabel = null;
                $currentText = [];
            }
        }

        if ($currentLabel !== null) {
            $entities[] = new Entity(implode(' ', $currentText), $currentLabel, $start, $end, 0.72);
        }

        return $entities;
    }

    /** @param array<int, string> $words @return array<int, string> */
    private function predictLabels(array $words): array
    {
        $out = [];
        foreach ($words as $i => $word) {
            $prev = $i > 0 ? $out[$i - 1] : '<S>';
            $features = $this->features($words, $i, $prev);
            $scores = array_fill_keys($this->labels, 0.0);

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
    private function features(array $words, int $i, string $prevLabel): array
    {
        $w = $words[$i] ?? '';
        $n = mb_strtolower($w);
        $prev = mb_strtolower($words[$i - 1] ?? '<S>');
        $next = mb_strtolower($words[$i + 1] ?? '</S>');

        return [
            'bias',
            'w=' . $n,
            'p1=' . mb_substr($n, 0, 1),
            's3=' . mb_substr($n, -3),
            'prev=' . $prev,
            'next=' . $next,
            'prevLabel=' . $prevLabel,
            'caps=' . (preg_match('/^\p{Lu}/u', $w) === 1 ? '1' : '0'),
            'digit=' . (preg_match('/^\d+$/', $n) === 1 ? '1' : '0'),
        ];
    }
}
