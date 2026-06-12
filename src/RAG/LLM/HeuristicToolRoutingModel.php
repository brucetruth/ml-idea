<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\LLM;

use ML\IDEA\RAG\Contracts\ToolRoutingModelInterface;

/**
 * Local fallback router for deterministic demos/tests.
 */
final class HeuristicToolRoutingModel implements ToolRoutingModelInterface
{
    public function respond(array $messages, array $tools): array
    {
        $lastUser = '';
        $lastToolOutput = null;

        for ($i = count($messages) - 1; $i >= 0; $i--) {
            $msg = $messages[$i];
            if ($lastToolOutput === null && $msg['role'] === 'tool') {
                $lastToolOutput = $msg['content'];
            }
            if ($msg['role'] === 'user') {
                $lastUser = strtolower($msg['content']);
                break;
            }
        }

        if (is_string($lastToolOutput) && $lastToolOutput !== '') {
            return ['type' => 'final', 'content' => 'Tool result: ' . $lastToolOutput];
        }

        $multiCalls = [];

        if ($this->hasTool($tools, 'math') && preg_match('/[0-9].*[\+\-\*\/\^]|sin\(|cos\(|tan\(|sqrt\(/', $lastUser) === 1) {
            $multiCalls[] = ['tool' => 'math', 'input' => ['expression' => $this->extractExpression($lastUser)]];
        }

        if ($this->hasTool($tools, 'weather') && (str_contains($lastUser, 'weather') || str_contains($lastUser, 'temperature'))) {
            $coords = $this->extractCoordinates($lastUser);
            if ($coords === null) {
                return ['type' => 'clarify', 'content' => 'Which latitude and longitude should I use for the weather lookup?'];
            }
            $multiCalls[] = ['tool' => 'weather', 'input' => $coords];
        }

        if ($this->hasTool($tools, 'db_query') && (str_contains($lastUser, 'sql') || str_contains($lastUser, 'database') || str_contains($lastUser, 'db ') || str_contains($lastUser, 'orders') || str_contains($lastUser, 'customers'))) {
            $multiCalls[] = [
                'tool' => 'db_query',
                'input' => [
                    'sql' => $this->inferSql($lastUser),
                    'params' => [],
                ],
            ];
        }

        if ($this->hasTool($tools, 'rag_qa') && $this->looksLikeKnowledgeQuestion($lastUser)) {
            $multiCalls[] = ['tool' => 'rag_qa', 'input' => ['question' => $lastUser, 'k' => 3]];
        }

        if (count($multiCalls) > 1) {
            return ['type' => 'tool_calls', 'tool_calls' => $multiCalls];
        }

        if (count($multiCalls) === 1) {
            return ['type' => 'tool_call', 'tool' => $multiCalls[0]['tool'], 'input' => $multiCalls[0]['input']];
        }

        return ['type' => 'final', 'content' => 'No suitable tool found.'];
    }

    /** @param array<int, array{name: string, description: string}> $tools */
    private function hasTool(array $tools, string $name): bool
    {
        foreach ($tools as $tool) {
            if ($tool['name'] === $name) {
                return true;
            }
        }
        return false;
    }

    private function extractExpression(string $text): string
    {
        if (preg_match('/([0-9\s\+\-\*\/\^\(\)\.]+|(?:sin|cos|tan|sqrt|log|ln|exp|pow|min|max)\([^\)]+\))/i', $text, $m) === 1) {
            return trim($m[0]);
        }

        return $text;
    }

    /** @return array{lat: float, lon: float}|null */
    private function extractCoordinates(string $text): ?array
    {
        if (preg_match('/lat(?:itude)?\s*[:=]?\s*(-?\d+(?:\.\d+)?).*lon(?:gitude)?\s*[:=]?\s*(-?\d+(?:\.\d+)?)/i', $text, $m) === 1) {
            return ['lat' => (float) $m[1], 'lon' => (float) $m[2]];
        }

        if (preg_match('/(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)/', $text, $m) === 1) {
            return ['lat' => (float) $m[1], 'lon' => (float) $m[2]];
        }

        return null;
    }

    private function inferSql(string $text): string
    {
        if (str_contains($text, 'customers') && !str_contains($text, 'orders')) {
            return 'SELECT * FROM customers LIMIT 5';
        }

        return 'SELECT * FROM orders LIMIT 5';
    }

    private function looksLikeKnowledgeQuestion(string $text): bool
    {
        return str_contains($text, '?')
            || str_contains($text, 'summarize')
            || str_contains($text, 'explain')
            || str_contains($text, 'what')
            || str_contains($text, 'how')
            || str_contains($text, 'why')
            || str_contains($text, 'kb')
            || str_contains($text, 'knowledge');
    }
}
