<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Examples\AiAdmin\Support\AdminStore;
use ML\IDEA\Examples\AiAdmin\Tools\RefundOrderTool;
use ML\IDEA\RAG\Agents\AgentContextManager;
use ML\IDEA\RAG\Agents\AgentState;
use ML\IDEA\RAG\Agents\AgentStateStoreFactory;
use ML\IDEA\RAG\Agents\FileAgentMemoryStore;
use ML\IDEA\RAG\Agents\InMemoryToolIdempotencyStore;
use ML\IDEA\RAG\Agents\ToolCallBatchPlanner;
use ML\IDEA\RAG\Agents\ToolExecutor;
use ML\IDEA\RAG\Agents\ToolRoutingAgent;
use ML\IDEA\RAG\Contracts\ToolRoutingModelInterface;
use ML\IDEA\RAG\Contracts\ToolSchemaInterface;
use ML\IDEA\RAG\Tools\MathTool;
use PHPUnit\Framework\TestCase;

final class RagAgentV19Test extends TestCase
{
    public function testIdempotencyStorePreventsDoubleRefund(): void
    {
        $store = new AdminStore();
        $tool = new RefundOrderTool($store);
        $executor = new ToolExecutor(idempotencyStore: new InMemoryToolIdempotencyStore());
        $input = ['order_id' => 101, 'reason' => 'duplicate charge'];

        $first = $executor->execute($tool, $input, approvalGranted: true);
        $second = $executor->execute($tool, $input, approvalGranted: true);

        self::assertTrue($first->ok);
        self::assertFalse($first->idempotentReplay);
        self::assertTrue($second->ok);
        self::assertTrue($second->idempotentReplay);
        self::assertSame('refunded', $store->listOrders(null, 'refunded')[0]['status'] ?? null);
    }

    public function testToolCallBatchPlannerOrdersLowRiskFirst(): void
    {
        $high = new class () implements \ML\IDEA\RAG\Contracts\ToolInterface, ToolSchemaInterface {
            public function name(): string { return 'refund_order'; }
            public function description(): string { return 'refund'; }
            public function invoke(array $input): string { return '{"ok":true}'; }
            public function inputSchema(): array { return ['type' => 'object']; }
            public function examples(): array { return []; }
            public function riskLevel(): string { return 'high'; }
        };
        $low = new class () implements \ML\IDEA\RAG\Contracts\ToolInterface, ToolSchemaInterface {
            public function name(): string { return 'get_user'; }
            public function description(): string { return 'get'; }
            public function invoke(array $input): string { return '{"ok":true}'; }
            public function inputSchema(): array { return ['type' => 'object']; }
            public function examples(): array { return []; }
            public function riskLevel(): string { return 'low'; }
        };

        $ordered = ToolCallBatchPlanner::orderByRisk([
            ['tool' => 'refund_order', 'input' => ['order_id' => 1]],
            ['tool' => 'get_user', 'input' => ['user_id' => 2]],
        ], [
            'refund_order' => $high,
            'get_user' => $low,
        ]);

        self::assertSame('get_user', $ordered[0]['tool']);
        self::assertSame('refund_order', $ordered[1]['tool']);
    }

    public function testEpisodicMemorySummarizesSessionEpisodes(): void
    {
        $path = sys_get_temp_dir() . '/mlidea_memory_' . uniqid('', true);
        $memory = new FileAgentMemoryStore($path);
        $memory->append('sess-1', ['tool' => 'get_user', 'ok' => true, 'note' => 'found Bob']);
        $memory->append('sess-1', ['tool' => 'tag_order', 'ok' => true, 'note' => 'billing-review']);

        $summary = $memory->summarizeForContext('sess-1');
        self::assertStringContainsString('get_user', $summary);
        self::assertStringContainsString('tag_order', $summary);
    }

    public function testAgentUsesMemoryOnWindowedSession(): void
    {
        $memoryPath = sys_get_temp_dir() . '/mlidea_memory_' . uniqid('', true);
        $sessionPath = sys_get_temp_dir() . '/mlidea_sess_' . uniqid('', true);
        $memory = new FileAgentMemoryStore($memoryPath);

        $model = new class () implements ToolRoutingModelInterface {
            private int $turn = 0;

            public function respond(array $messages, array $tools): array
            {
                $this->turn++;
                foreach ($messages as $message) {
                    if (str_contains((string) ($message['content'] ?? ''), 'Episodic memory')) {
                        return ['type' => 'final', 'content' => 'I remember prior tool work.'];
                    }
                }

                return ['type' => 'final', 'content' => 'No memory yet.'];
            }
        };

        $agent = new ToolRoutingAgent(
            $model,
            [new MathTool()],
            contextManager: new AgentContextManager(maxRoutingMessages: 6, maxToolMessageChars: 200, preserveInitialMessages: 1),
            stateStore: AgentStateStoreFactory::create(['driver' => 'file', 'path' => $sessionPath]),
            memoryStore: $memory,
        );

        $sessionId = 'memory-demo';
        $agent->chatInSession($sessionId, 'step 1');
        $memory->append($sessionId, ['tool' => 'get_user', 'ok' => true, 'note' => 'User Bob active']);

        for ($i = 0; $i < 8; $i++) {
            $agent->chatInSession($sessionId, 'filler turn ' . $i . ' ' . str_repeat('x', 80));
        }

        $result = $agent->chatInSession($sessionId, 'what happened earlier?');
        self::assertStringContainsString('remember', strtolower($result['answer']));
    }
}
