<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\RAG\Agents\InMemoryAgentMemoryStore;
use ML\IDEA\RAG\Agents\LlmEpisodicMemorySummarizer;
use ML\IDEA\RAG\Agents\ParallelToolCallRunner;
use ML\IDEA\RAG\Agents\ToolCircuitBreaker;
use ML\IDEA\RAG\Agents\ToolExecutor;
use ML\IDEA\RAG\Agents\TruncatingEpisodicMemorySummarizer;
use ML\IDEA\RAG\Contracts\ParallelInvokableToolInterface;
use ML\IDEA\RAG\Contracts\RetryableToolInterface;
use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Contracts\ToolSchemaInterface;
use ML\IDEA\RAG\LLM\EchoLlmClient;
use ML\IDEA\RAG\Tools\MathTool;
use PHPUnit\Framework\TestCase;

final class RagAgentV20Test extends TestCase
{
    public function testCircuitBreakerOpensAfterRepeatedFailures(): void
    {
        $breaker = new ToolCircuitBreaker(failureThreshold: 2, cooldownSeconds: 60);
        $tool = new class () implements ToolInterface, RetryableToolInterface {
            public function name(): string
            {
                return 'flaky';
            }

            public function description(): string
            {
                return 'always fails';
            }

            public function invoke(array $input): string
            {
                throw new \RuntimeException('boom');
            }

            public function isRetryable(): bool
            {
                return false;
            }
        };

        $executor = new ToolExecutor(circuitBreaker: $breaker);
        $first = $executor->execute($tool, []);
        $second = $executor->execute($tool, []);
        $third = $executor->execute($tool, []);

        self::assertFalse($first->ok);
        self::assertFalse($second->ok);
        self::assertFalse($third->ok);
        self::assertSame('circuit_open', $third->errorType);
        self::assertTrue($breaker->isOpen('flaky'));
    }

    public function testLlmEpisodicMemorySummarizerUsesLlmWithFallbackShape(): void
    {
        $memory = new InMemoryAgentMemoryStore(new LlmEpisodicMemorySummarizer(new EchoLlmClient()));
        $memory->append('s1', ['tool' => 'get_user', 'ok' => true, 'note' => 'Bob active']);
        $memory->append('s1', ['tool' => 'tag_order', 'ok' => true, 'note' => 'billing']);

        $summary = $memory->summarizeForContext('s1');
        self::assertStringStartsWith('MEMORY:', $summary);
        self::assertStringContainsString('get_user', $summary);
    }

    public function testTruncatingSummarizerStillWorksAsDefault(): void
    {
        $memory = new InMemoryAgentMemoryStore();
        $memory->append('s1', ['tool' => 'math', 'ok' => true]);

        self::assertStringContainsString('math', $memory->summarizeForContext('s1'));
    }

    public function testParallelRunnerSequentialFallbackForMathTools(): void
    {
        $math = new MathTool();
        $calls = [
            ['tool' => 'math', 'input' => ['expression' => '2+2']],
            ['tool' => 'math', 'input' => ['expression' => 'sqrt(81)']],
        ];
        $runner = new ParallelToolCallRunner(true, null);
        self::assertTrue($runner->canParallelizeBatch($calls, ['math' => $math]));

        $batch = $runner->run($calls, ['math' => $math], fn (array $call): string => $math->invoke($call['input']));
        self::assertContains($batch['mode'], ['sequential_fallback', 'sequential']);
        self::assertStringContainsString('4', $batch['outputs'][0]);
        self::assertStringContainsString('9', $batch['outputs'][1]);
    }

    public function testParallelRunnerRejectsHighRiskTools(): void
    {
        $high = new class () implements ToolInterface, ToolSchemaInterface, ParallelInvokableToolInterface {
            public function name(): string
            {
                return 'danger';
            }

            public function description(): string
            {
                return 'high risk';
            }

            public function invoke(array $input): string
            {
                return self::invokeParallel($input);
            }

            public static function invokeParallel(array $input): string
            {
                return '{"ok":true}';
            }

            public function inputSchema(): array
            {
                return ['type' => 'object'];
            }

            public function examples(): array
            {
                return [];
            }

            public function riskLevel(): string
            {
                return 'high';
            }
        };

        $runner = new ParallelToolCallRunner(true, __DIR__ . '/../vendor/autoload.php');
        self::assertFalse($runner->canParallelizeBatch(
            [['tool' => 'danger', 'input' => []], ['tool' => 'danger', 'input' => []]],
            ['danger' => $high],
        ));
    }
}
