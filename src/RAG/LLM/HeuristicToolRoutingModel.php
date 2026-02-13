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

        if ($this->hasTool($tools, 'math') && preg_match('/[0-9].*[\+\-\*\/\^]|sin\(|cos\(|tan\(|sqrt\(/', $lastUser) === 1) {
            return ['type' => 'tool_call', 'tool' => 'math', 'input' => ['expression' => $this->extractExpression($lastUser)]];
        }

        if ($this->hasTool($tools, 'weather') && (str_contains($lastUser, 'weather') || str_contains($lastUser, 'temperature'))) {
            return ['type' => 'tool_call', 'tool' => 'weather', 'input' => ['lat' => -15.3875, 'lon' => 28.3228]];
        }

        if ($this->hasTool($tools, 'db_query') && (str_contains($lastUser, 'sql') || str_contains($lastUser, 'database') || str_contains($lastUser, 'db ') || str_contains($lastUser, 'orders') || str_contains($lastUser, 'customers'))) {
            return [
                'type' => 'tool_call',
                'tool' => 'db_query',
                'input' => [
                    'sql' => 'SELECT * FROM orders LIMIT 5',
                    'params' => [],
                ],
            ];
        }

        if ($this->hasTool($tools, 'rag_qa')) {
            return ['type' => 'tool_call', 'tool' => 'rag_qa', 'input' => ['question' => $lastUser, 'k' => 3]];
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
}
