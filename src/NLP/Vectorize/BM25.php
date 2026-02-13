<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Vectorize;

use ML\IDEA\NLP\Tokenize\UnicodeWordTokenizer;

final class BM25
{
    /** @var array<int, array<int, string>> */
    private array $docs = [];
    /** @var array<int, string> */
    private array $rawDocs = [];
    /** @var array<string, int> */
    private array $df = [];
    /** @var array<int, int> */
    private array $docLen = [];
    private float $avgDocLen = 0.0;

    public function __construct(private readonly float $k1 = 1.5, private readonly float $b = 0.75)
    {
    }

    public function addDocuments(array $documents): void
    {
        $tok = new UnicodeWordTokenizer();
        foreach ($documents as $doc) {
            $tokens = array_map(static fn ($t): string => $t->norm, $tok->tokenize((string) $doc));
            $id = count($this->docs);
            $this->docs[$id] = $tokens;
            $this->rawDocs[$id] = (string) $doc;
        }
    }

    public function build(): void
    {
        $this->df = [];
        $this->docLen = [];
        $sum = 0;

        foreach ($this->docs as $id => $tokens) {
            $this->docLen[$id] = count($tokens);
            $sum += count($tokens);

            $seen = [];
            foreach ($tokens as $t) {
                if (isset($seen[$t])) {
                    continue;
                }
                $seen[$t] = true;
                $this->df[$t] = ($this->df[$t] ?? 0) + 1;
            }
        }

        $this->avgDocLen = $this->docs === [] ? 0.0 : $sum / count($this->docs);
    }

    /** @return array<int, array{id:int, score:float, text:string}> */
    public function search(string $query, int $topK = 5): array
    {
        $tok = new UnicodeWordTokenizer();
        $qTerms = array_map(static fn ($t): string => $t->norm, $tok->tokenize($query));
        if ($qTerms === [] || $this->docs === []) {
            return [];
        }

        $N = count($this->docs);
        $scores = [];

        foreach ($this->docs as $id => $tokens) {
            $tf = [];
            foreach ($tokens as $t) {
                $tf[$t] = ($tf[$t] ?? 0) + 1;
            }

            $score = 0.0;
            foreach ($qTerms as $term) {
                $df = $this->df[$term] ?? 0;
                if ($df === 0) {
                    continue;
                }
                $idf = log(1.0 + (($N - $df + 0.5) / ($df + 0.5)));
                $f = $tf[$term] ?? 0;
                if ($f === 0) {
                    continue;
                }

                $dl = $this->docLen[$id] ?? 1;
                $den = $f + $this->k1 * (1.0 - $this->b + $this->b * ($dl / max(1e-12, $this->avgDocLen)));
                $score += $idf * (($f * ($this->k1 + 1.0)) / max(1e-12, $den));
            }

            $scores[$id] = $score;
        }

        arsort($scores);
        $rows = [];
        foreach (array_slice($scores, 0, $topK, true) as $id => $score) {
            $rows[] = ['id' => (int) $id, 'score' => $score, 'text' => $this->rawDocs[(int) $id] ?? ''];
        }
        return $rows;
    }
}
