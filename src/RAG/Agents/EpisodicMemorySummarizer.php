<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

final class EpisodicMemorySummarizer
{
    /**
     * @param array<int, array<string, mixed>> $episodes
     */
    public static function summarize(array $episodes, int $maxChars = 1200): string
    {
        if ($episodes === []) {
            return '';
        }

        $lines = ['Episodic memory from earlier in this session:'];
        foreach ($episodes as $episode) {
            $tool = (string) ($episode['tool'] ?? 'step');
            $ok = ($episode['ok'] ?? false) === true ? 'ok' : 'failed';
            $note = (string) ($episode['note'] ?? $episode['summary'] ?? '');
            $lines[] = $note !== ''
                ? sprintf('- %s (%s): %s', $tool, $ok, $note)
                : sprintf('- %s (%s)', $tool, $ok);
        }

        $text = implode("\n", $lines);
        if (strlen($text) <= $maxChars) {
            return $text;
        }

        return substr($text, 0, $maxChars - 3) . '...';
    }
}
