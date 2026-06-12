<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\RAG\Agents\AgentState;
use ML\IDEA\RAG\Agents\JsonFileAgentStateStore;
use ML\IDEA\RAG\Agents\ToolRoutingAgent;
use ML\IDEA\RAG\Contracts\ToolRoutingModelInterface;
use ML\IDEA\RAG\Tools\MathTool;
use PHPUnit\Framework\TestCase;

final class RagAgentSessionTest extends TestCase
{
    public function testChatInSessionLoadsAppendsAndPersistsState(): void
    {
        $dir = sys_get_temp_dir() . '/ml_idea_agent_session_' . uniqid('', true);
        $store = new JsonFileAgentStateStore($dir);
        $model = new class () implements ToolRoutingModelInterface {
            private int $turn = 0;

            public function respond(array $messages, array $tools): array
            {
                $this->turn++;
                return $this->turn === 1
                    ? ['type' => 'tool_call', 'tool' => 'math', 'input' => ['expression' => '1+1']]
                    : ['type' => 'final', 'content' => 'second turn'];
            }
        };

        $agent = new ToolRoutingAgent($model, [new MathTool()], stateStore: $store);
        $first = $agent->chatInSession('session-a', 'first question');
        self::assertSame('session-a', $first['session_id']);
        self::assertTrue($store->exists('session-a'));

        $second = $agent->chatInSession('session-a', 'follow up');
        self::assertSame('second turn', $second['answer']);
        self::assertGreaterThan(count($first['trace']), count($second['trace']));

        @unlink($dir . '/session-a.json');
        @rmdir($dir);
    }

    public function testChatInSessionRequiresStateStore(): void
    {
        $agent = new ToolRoutingAgent(new class () implements ToolRoutingModelInterface {
            public function respond(array $messages, array $tools): array
            {
                return ['type' => 'final', 'content' => 'ok'];
            }
        }, [new MathTool()]);

        $this->expectException(\InvalidArgumentException::class);
        $agent->chatInSession('missing-store', 'hello');
    }

    public function testResumeWithApprovalInSessionRejectsInvalidToken(): void
    {
        $dir = sys_get_temp_dir() . '/ml_idea_agent_session_' . uniqid('', true);
        $store = new JsonFileAgentStateStore($dir);

        $pausedState = new AgentState('risky task', 'system');
        $pausedState->setPendingApproval([
            'tool' => 'risky',
            'input' => ['payload' => 'ok'],
            'provider_call_id' => '',
            'risk_level' => 'high',
            'approval_token' => 'abc123token45678',
            'iteration' => 1,
        ]);
        $store->save('risk-session', $pausedState);

        $agent = new ToolRoutingAgent(
            new class () implements ToolRoutingModelInterface {
                public function respond(array $messages, array $tools): array
                {
                    return ['type' => 'final', 'content' => 'after approval'];
                }
            },
            [new class () implements \ML\IDEA\RAG\Contracts\ToolInterface, \ML\IDEA\RAG\Contracts\ToolSchemaInterface {
                public function name(): string { return 'risky'; }
                public function description(): string { return 'Risky.'; }
                public function invoke(array $input): string { return 'ok'; }
                public function inputSchema(): array { return ['type' => 'object', 'required' => ['payload'], 'properties' => ['payload' => ['type' => 'string']]]; }
                public function examples(): array { return [['payload' => 'ok']]; }
                public function riskLevel(): string { return 'high'; }
            }],
            stateStore: $store,
            toolExecutor: new \ML\IDEA\RAG\Agents\ToolExecutor(
                policy: new \ML\IDEA\RAG\Agents\AgentPolicy(pauseForApproval: true)
            ),
        );

        $this->expectException(\InvalidArgumentException::class);
        $agent->resumeWithApprovalInSession('risk-session', true, 'wrong-token');

        @unlink($dir . '/risk-session.json');
        @rmdir($dir);
    }

    public function testResumeWithApprovalInSessionPersistsUpdatedState(): void
    {
        $dir = sys_get_temp_dir() . '/ml_idea_agent_session_' . uniqid('', true);
        $store = new JsonFileAgentStateStore($dir);

        $pausedState = new AgentState('risky task', 'system');
        $pausedState->setPendingApproval([
            'tool' => 'risky',
            'input' => ['payload' => 'ok'],
            'provider_call_id' => '',
            'risk_level' => 'high',
            'approval_token' => 'abc123token45678',
            'iteration' => 1,
        ]);
        $store->save('risk-session', $pausedState);

        $agent = new ToolRoutingAgent(
            new class () implements ToolRoutingModelInterface {
                public function respond(array $messages, array $tools): array
                {
                    return ['type' => 'final', 'content' => 'after approval'];
                }
            },
            [new class () implements \ML\IDEA\RAG\Contracts\ToolInterface, \ML\IDEA\RAG\Contracts\ToolSchemaInterface {
                public function name(): string { return 'risky'; }
                public function description(): string { return 'Risky.'; }
                public function invoke(array $input): string { return 'ok'; }
                public function inputSchema(): array { return ['type' => 'object', 'required' => ['payload'], 'properties' => ['payload' => ['type' => 'string']]]; }
                public function examples(): array { return [['payload' => 'ok']]; }
                public function riskLevel(): string { return 'high'; }
            }],
            stateStore: $store,
            toolExecutor: new \ML\IDEA\RAG\Agents\ToolExecutor(
                policy: new \ML\IDEA\RAG\Agents\AgentPolicy(pauseForApproval: true)
            ),
        );

        $result = $agent->resumeWithApprovalInSession('risk-session', true, 'abc123token45678');
        self::assertSame('after approval', $result['answer']);
        self::assertSame('risk-session', $result['session_id']);
        self::assertNull(AgentState::fromArray($store->load('risk-session')?->toArray() ?? [])->pendingApproval());

        @unlink($dir . '/risk-session.json');
        @rmdir($dir);
    }
}
