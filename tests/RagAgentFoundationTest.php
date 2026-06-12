<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\RAG\Agents\AgentContextManager;
use ML\IDEA\RAG\Agents\AgentEvalCase;
use ML\IDEA\RAG\Agents\AgentEvalHarness;
use ML\IDEA\RAG\Agents\ToolExecutor;
use ML\IDEA\RAG\Agents\ToolReliabilityPolicy;
use ML\IDEA\RAG\Agents\ToolRoutingAgent;
use ML\IDEA\RAG\Contracts\RetryableToolInterface;
use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Contracts\ToolRoutingModelInterface;
use ML\IDEA\RAG\LLM\HeuristicToolRoutingModel;
use ML\IDEA\RAG\Tools\MathTool;
use PHPUnit\Framework\TestCase;

final class RagAgentFoundationTest extends TestCase
{
    public function testAgentContextManagerCompressesToolMessages(): void
    {
        $manager = new AgentContextManager(maxRoutingMessages: 20, maxToolMessageChars: 20, preserveInitialMessages: 2);
        $messages = [
            ['role' => 'system', 'content' => 'system prompt'],
            ['role' => 'user', 'content' => 'goal'],
            ['role' => 'tool', 'content' => str_repeat('x', 100)],
        ];

        $prepared = $manager->prepareForRouting($messages);
        self::assertStringContainsString('[context_truncated]', (string) $prepared[2]['content']);
    }

    public function testAgentContextManagerWindowsLongHistories(): void
    {
        $manager = new AgentContextManager(maxRoutingMessages: 5, maxToolMessageChars: 4000, preserveInitialMessages: 2);
        $messages = [
            ['role' => 'system', 'content' => 'system prompt'],
            ['role' => 'user', 'content' => 'goal'],
            ['role' => 'assistant', 'content' => 'older turn'],
            ['role' => 'assistant', 'content' => 'middle turn'],
            ['role' => 'assistant', 'content' => 'recent turn'],
            ['role' => 'user', 'content' => 'latest question'],
        ];

        $prepared = $manager->prepareForRouting($messages);

        self::assertLessThanOrEqual(5, count($prepared));
        self::assertSame('latest question', $prepared[array_key_last($prepared)]['content']);
        self::assertTrue(
            (bool) array_filter(
                $prepared,
                static fn (array $message): bool => str_contains((string) ($message['content'] ?? ''), 'earlier messages omitted')
            )
        );
    }

    public function testToolExecutorRetriesRetryableTools(): void
    {
        $tool = new class () implements ToolInterface, RetryableToolInterface {
            private int $attempts = 0;

            public function name(): string
            {
                return 'flaky';
            }

            public function description(): string
            {
                return 'Fails once then succeeds.';
            }

            public function isRetryable(): bool
            {
                return true;
            }

            public function invoke(array $input): string
            {
                $this->attempts++;
                if ($this->attempts === 1) {
                    throw new \RuntimeException('transient');
                }

                return 'ok';
            }
        };

        $executor = new ToolExecutor(reliability: new ToolReliabilityPolicy(maxAttempts: 2));
        $result = $executor->execute($tool, []);

        self::assertTrue($result->ok);
        self::assertSame(2, $result->attempts);
    }

    public function testToolRoutingAgentIncludesPlanningPromptByDefault(): void
    {
        $model = new class () implements ToolRoutingModelInterface {
            public function respond(array $messages, array $tools): array
            {
                return ['type' => 'final', 'content' => 'ok'];
            }
        };

        $prompt = (new ToolRoutingAgent($model, [new MathTool()]))->getSystemPrompt();
        self::assertStringContainsString('plan-act-observe-reflect-final', $prompt);
    }

    public function testToolRoutingAgentRecordsDecisionConfidence(): void
    {
        $model = new class () implements ToolRoutingModelInterface {
            public function respond(array $messages, array $tools): array
            {
                return ['type' => 'final', 'content' => 'done', 'confidence' => 0.91];
            }
        };

        $result = (new ToolRoutingAgent($model, [new MathTool()]))->chat('hello');
        self::assertSame(0.91, $result['decisions'][0]['confidence'] ?? null);
    }

    public function testAgentEvalHarnessRunsHeuristicFixtures(): void
    {
        $weather = new class () implements ToolInterface, \ML\IDEA\RAG\Contracts\ToolSchemaInterface {
            public function name(): string { return 'weather'; }
            public function description(): string { return 'Weather stub.'; }
            public function invoke(array $input): string { return json_encode(['weather' => $input], JSON_THROW_ON_ERROR); }
            public function inputSchema(): array { return ['type' => 'object', 'required' => ['lat', 'lon'], 'properties' => ['lat' => ['type' => 'number'], 'lon' => ['type' => 'number']]]; }
            public function examples(): array { return [['lat' => -15.3, 'lon' => 28.3]]; }
            public function riskLevel(): string { return 'low'; }
        };

        $agent = new ToolRoutingAgent(new HeuristicToolRoutingModel(), [new MathTool(), $weather]);
        $harness = new AgentEvalHarness();
        $cases = $harness->loadCasesFromJson(__DIR__ . '/fixtures/agent_eval_heuristic.json');
        $results = $harness->run($agent, $cases);
        $summary = $harness->summarize($results);

        self::assertSame(3, $summary['total']);
        self::assertSame(3, $summary['passed']);
        self::assertSame(1.0, $summary['pass_rate']);
    }

    public function testAgentEvalHarnessDetectsFailures(): void
    {
        $model = new class () implements ToolRoutingModelInterface {
            public function respond(array $messages, array $tools): array
            {
                return ['type' => 'final', 'content' => 'unexpected'];
            }
        };

        $harness = new AgentEvalHarness();
        $results = $harness->run(
            new ToolRoutingAgent($model, [new MathTool()]),
            [new AgentEvalCase('bad', 'hello', ['answer_contains' => 'missing-text'])]
        );

        self::assertFalse($results[0]->passed);
        self::assertNotSame('', $results[0]->message);
    }
}
