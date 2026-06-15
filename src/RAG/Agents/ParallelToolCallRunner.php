<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

use ML\IDEA\RAG\Contracts\ParallelInvokableToolInterface;
use ML\IDEA\RAG\Contracts\ToolSchemaInterface;

final class ParallelToolCallRunner
{
    public function __construct(
        private readonly bool $enabled = false,
        private readonly ?string $autoloadPath = null,
    ) {
    }

    /**
     * @param array<int, array{tool: string, input: array<string, mixed>}> $calls
     * @param array<string, \ML\IDEA\RAG\Contracts\ToolInterface> $tools
     * @param callable(array{tool: string, input: array<string, mixed>}): string $sequentialInvoke
     * @return array{mode: string, outputs: array<int, string>}
     */
    public function run(array $calls, array $tools, callable $sequentialInvoke): array
    {
        if (!$this->enabled || count($calls) <= 1 || !$this->canParallelize($calls, $tools)) {
            $outputs = [];
            foreach ($calls as $i => $call) {
                $outputs[$i] = $sequentialInvoke($call);
            }

            return ['mode' => 'sequential', 'outputs' => $outputs];
        }

        if (class_exists(\parallel\Runtime::class) && $this->autoloadPath !== null && is_file($this->autoloadPath)) {
            $outputs = $this->runWithParallelExtension($calls, $tools);
            if ($outputs !== null) {
                return ['mode' => 'parallel_ext', 'outputs' => $outputs];
            }
        }

        $outputs = [];
        foreach ($calls as $i => $call) {
            $outputs[$i] = $sequentialInvoke($call);
        }

        return ['mode' => 'sequential_fallback', 'outputs' => $outputs];
    }

    public static function isAvailable(): bool
    {
        return class_exists(\parallel\Runtime::class);
    }

    /**
     * @param array<int, array{tool: string, input: array<string, mixed>}> $calls
     * @param array<string, \ML\IDEA\RAG\Contracts\ToolInterface> $tools
     */
    public function canParallelizeBatch(array $calls, array $tools): bool
    {
        return $this->enabled && count($calls) > 1 && $this->canParallelize($calls, $tools);
    }

    /**
     * @param array<int, array{tool: string, input: array<string, mixed>}> $calls
     * @param array<string, \ML\IDEA\RAG\Contracts\ToolInterface> $tools
     */
    private function canParallelize(array $calls, array $tools): bool
    {
        foreach ($calls as $call) {
            $toolName = (string) ($call['tool'] ?? '');
            $tool = $tools[$toolName] ?? null;
            if (!$tool instanceof ParallelInvokableToolInterface) {
                return false;
            }
            if ($tool instanceof ToolSchemaInterface && $tool->riskLevel() === 'high') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, array{tool: string, input: array<string, mixed>}> $calls
     * @param array<string, \ML\IDEA\RAG\Contracts\ToolInterface> $tools
     * @return array<int, string>|null
     */
    private function runWithParallelExtension(array $calls, array $tools): ?array
    {
        try {
            /** @var array<int, \parallel\Future> $futures */
            $futures = [];

            foreach ($calls as $i => $call) {
                $tool = $tools[$call['tool']];
                $runtime = new \parallel\Runtime($this->autoloadPath);
                $futures[$i] = $runtime->run(
                    static function (string $toolClass, array $input): string {
                        return $toolClass::invokeParallel($input);
                    },
                    [$tool::class, $call['input']],
                );
            }

            $outputs = [];
            foreach ($futures as $i => $future) {
                $outputs[$i] = (string) $future->value();
            }

            return $outputs;
        } catch (\Throwable) {
            return null;
        }
    }
}
