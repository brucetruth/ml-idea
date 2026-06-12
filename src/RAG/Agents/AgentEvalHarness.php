<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

final class AgentEvalHarness
{
    /**
     * @param array<int, AgentEvalCase> $cases
     * @return array<int, AgentEvalResult>
     */
    public function run(ToolRoutingAgent $agent, array $cases): array
    {
        $results = [];
        foreach ($cases as $case) {
            $run = $agent->chat($case->prompt);
            $actual = [
                'stop_reason' => (string) ($run['stop_reason'] ?? ''),
                'answer' => (string) ($run['answer'] ?? ''),
                'tool_names' => array_map(
                    static fn (array $call): string => (string) ($call['name'] ?? ''),
                    is_array($run['tool_calls'] ?? null) ? $run['tool_calls'] : []
                ),
                'tool_call_count' => count(is_array($run['tool_calls'] ?? null) ? $run['tool_calls'] : []),
            ];
            [$passed, $message] = $this->matches($case->expect, $actual);
            $results[] = new AgentEvalResult($case->name, $passed, $case->expect, $actual, $message);
        }

        return $results;
    }

    /**
     * @param array<int, AgentEvalResult> $results
     * @return array{total: int, passed: int, failed: int, pass_rate: float}
     */
    public function summarize(array $results): array
    {
        $total = count($results);
        $passed = count(array_filter($results, static fn (AgentEvalResult $result): bool => $result->passed));

        return [
            'total' => $total,
            'passed' => $passed,
            'failed' => $total - $passed,
            'pass_rate' => $total === 0 ? 1.0 : round($passed / $total, 4),
        ];
    }

    /**
     * @return array<int, AgentEvalCase>
     */
    public function loadCasesFromJson(string $path): array
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException("Eval fixture not found: {$path}");
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \InvalidArgumentException("Unable to read eval fixture: {$path}");
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException("Invalid eval fixture JSON: {$path}");
        }

        $cases = [];
        foreach ($decoded as $entry) {
            if (is_array($entry)) {
                $cases[] = AgentEvalCase::fromArray($entry);
            }
        }

        return $cases;
    }

    /**
     * @param array<string, mixed> $expect
     * @param array<string, mixed> $actual
     * @return array{0: bool, 1: string}
     */
    private function matches(array $expect, array $actual): array
    {
        if ($expect === []) {
            return [true, ''];
        }

        if (isset($expect['stop_reason']) && (string) $expect['stop_reason'] !== (string) ($actual['stop_reason'] ?? '')) {
            return [false, sprintf('Expected stop_reason %s, got %s', $expect['stop_reason'], $actual['stop_reason'] ?? '')];
        }

        if (isset($expect['min_tool_calls']) && (int) $actual['tool_call_count'] < (int) $expect['min_tool_calls']) {
            return [false, sprintf('Expected at least %d tool calls, got %d', $expect['min_tool_calls'], $actual['tool_call_count'])];
        }

        if (isset($expect['max_tool_calls']) && (int) $actual['tool_call_count'] > (int) $expect['max_tool_calls']) {
            return [false, sprintf('Expected at most %d tool calls, got %d', $expect['max_tool_calls'], $actual['tool_call_count'])];
        }

        if (isset($expect['tool_names']) && is_array($expect['tool_names'])) {
            /** @var array<int, string> $expectedTools */
            $expectedTools = array_map(static fn (mixed $name): string => (string) $name, $expect['tool_names']);
            /** @var array<int, string> $actualTools */
            $actualTools = is_array($actual['tool_names'] ?? null) ? $actual['tool_names'] : [];
            if ($expectedTools !== $actualTools) {
                return [false, sprintf('Expected tools [%s], got [%s]', implode(', ', $expectedTools), implode(', ', $actualTools))];
            }
        }

        $answer = (string) ($actual['answer'] ?? '');
        foreach ($this->stringList($expect['answer_contains'] ?? null) as $needle) {
            if (!str_contains($answer, $needle)) {
                return [false, sprintf('Expected answer to contain: %s', $needle)];
            }
        }

        foreach ($this->stringList($expect['answer_not_contains'] ?? null) as $needle) {
            if (str_contains($answer, $needle)) {
                return [false, sprintf('Expected answer not to contain: %s', $needle)];
            }
        }

        return [true, ''];
    }

    /** @return array<int, string> */
    private function stringList(mixed $value): array
    {
        if (is_string($value)) {
            return [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn (mixed $item): string => (string) $item, $value), static fn (string $item): bool => $item !== ''));
    }
}
