<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\RAG\Agents\AgentRunLogEntry;
use ML\IDEA\RAG\Agents\AgentRunLogContext;
use ML\IDEA\RAG\Agents\CallbackAgentRunLogger;
use ML\IDEA\RAG\Agents\JsonlAgentRunLogger;
use ML\IDEA\RAG\Agents\MultiAgentRunLogger;
use ML\IDEA\RAG\Agents\NoOpAgentRunLogger;
use ML\IDEA\RAG\Agents\ToolRoutingAgent;
use ML\IDEA\RAG\Contracts\ToolRoutingModelInterface;
use ML\IDEA\RAG\Tools\MathTool;
use PHPUnit\Framework\TestCase;

final class RagAgentRunLoggerTest extends TestCase
{
    public function testNoOpLoggerDoesNotThrow(): void
    {
        $logger = new NoOpAgentRunLogger();
        $logger->log(new AgentRunLogEntry(
            id: 'abc',
            loggedAt: gmdate('c'),
            agentName: 'Test',
            sessionId: null,
            userMessage: 'hello',
            resume: false,
            answer: 'ok',
            stopReason: 'final',
            iterations: 1,
            toolCalls: [],
            decisions: [],
            usage: [],
            budget: [],
            telemetry: null,
            pendingApproval: null,
        ));

        self::assertTrue(true);
    }

    public function testJsonlLoggerAppendsRedactedRunRecord(): void
    {
        $path = sys_get_temp_dir() . '/mlidea_agent_run_log_' . uniqid('', true) . '.jsonl';
        $logger = new JsonlAgentRunLogger($path);

        $entry = AgentRunLogEntry::fromAgentResult(
            [
                'answer' => 'done',
                'stop_reason' => 'final',
                'iterations' => 1,
                'tool_calls' => [
                    [
                        'name' => 'math',
                        'input' => ['expression' => '2+2', 'api_key' => 'secret'],
                        'output' => '{"result":4}',
                    ],
                ],
                'decisions' => [['type' => 'tool_call', 'tool_calls' => []]],
                'usage' => ['total_tokens' => 10],
                'budget' => ['elapsed_ms' => 5],
            ],
            'AuditAgent',
            new AgentRunLogContext(sessionId: 'sess-1', userMessage: 'calc', resume: false),
        );

        $logger->log($entry);

        self::assertFileExists($path);
        $line = trim((string) file_get_contents($path));
        self::assertNotSame('', $line);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('AuditAgent', $decoded['agent_name']);
        self::assertSame('sess-1', $decoded['session_id']);
        self::assertSame('final', $decoded['stop_reason']);
        self::assertSame('[redacted]', $decoded['tool_calls'][0]['input']['api_key']);

        @unlink($path);
    }

    public function testCallbackAndMultiLoggers(): void
    {
        $entries = [];
        $callback = new CallbackAgentRunLogger(static function (AgentRunLogEntry $entry) use (&$entries): void {
            $entries[] = $entry->id;
        });

        $entry = AgentRunLogEntry::fromAgentResult(
            ['answer' => 'ok', 'stop_reason' => 'final', 'iterations' => 1, 'tool_calls' => [], 'decisions' => [], 'usage' => [], 'budget' => []],
            'Test',
        );
        $callback->log($entry);

        self::assertCount(1, $entries);

        $path = sys_get_temp_dir() . '/mlidea_multi_log_' . uniqid('', true) . '.jsonl';
        $multi = new MultiAgentRunLogger([
            $callback,
            new JsonlAgentRunLogger($path),
        ]);
        $multi->log($entry);

        self::assertCount(2, $entries);
        self::assertFileExists($path);
        @unlink($path);
    }

    public function testToolRoutingAgentLogsOnEveryRun(): void
    {
        $path = sys_get_temp_dir() . '/mlidea_agent_run_log_' . uniqid('', true) . '.jsonl';
        $logger = new JsonlAgentRunLogger($path);

        $model = new class () implements ToolRoutingModelInterface {
            public function respond(array $messages, array $tools): array
            {
                return ['type' => 'final', 'content' => 'hello'];
            }
        };

        (new ToolRoutingAgent(
            $model,
            [],
            agentName: 'LoggedAgent',
            agentRunLogger: $logger,
        ))->chat('hi');

        self::assertFileExists($path);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode(trim((string) file_get_contents($path)), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('LoggedAgent', $decoded['agent_name']);
        self::assertSame('hi', $decoded['user_message']);
        self::assertSame('hello', $decoded['answer']);

        @unlink($path);
    }

    public function testToolRoutingAgentLogsSessionIdForChatInSession(): void
    {
        $path = sys_get_temp_dir() . '/mlidea_agent_run_log_' . uniqid('', true) . '.jsonl';
        $logger = new JsonlAgentRunLogger($path);

        $model = new class () implements ToolRoutingModelInterface {
            public function respond(array $messages, array $tools): array
            {
                return ['type' => 'final', 'content' => 'ok'];
            }
        };

        $storePath = sys_get_temp_dir() . '/mlidea_agent_session_' . uniqid('', true);
        $agent = new ToolRoutingAgent(
            $model,
            [],
            stateStore: \ML\IDEA\RAG\Agents\AgentStateStoreFactory::create(['driver' => 'file', 'path' => $storePath]),
            agentRunLogger: $logger,
        );

        $agent->chatInSession('billing-42', 'check order');

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode(trim((string) file_get_contents($path)), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('billing-42', $decoded['session_id']);
        self::assertSame('check order', $decoded['user_message']);

        @unlink($path);
    }
}
