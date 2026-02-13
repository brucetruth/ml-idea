<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Rag;

final class CitationFormatter
{
    /**
     * @param array<int, array{id:int, score:float, text:string}> $hits
     */
    public function format(array $hits): string
    {
        $lines = [];
        foreach ($hits as $i => $hit) {
            $lines[] = sprintf('[%d] doc=%d score=%.4f %s', $i + 1, $hit['id'], $hit['score'], $hit['text']);
        }

        return implode(PHP_EOL, $lines);
    }
}
