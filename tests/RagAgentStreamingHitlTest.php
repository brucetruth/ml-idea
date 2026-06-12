<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\RAG\Agents\AgentPolicy;
use ML\IDEA\RAG\Agents\AgentState;
use ML\IDEA\RAG\Agents\AgentStreamEvent;
use ML\IDEA\RAG\Agents\ToolExecutor;
use ML\IDEA\RAG\Agents\ToolInputValidator;
use ML\IDEA\RAG\Agents\ToolRoutingAgent;
use ML\IDEA\RAG\Contracts\StreamingToolRoutingModelInterface;
use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Contracts\ToolRoutingModelInterface;
use ML\IDEA\RAG\Contracts\ToolSchemaInterface;
use ML\IDEA\RAG\Tools\MathTool;
use PHPUnit\Framework\TestCase;

final class RagAgentStreamingHitlTest extends TestCase
{
    public function testChatStreamYieldsLifecycleEvents(): void
    {
        $model = new class () implements ToolRoutingModelInterface {
            private int $turn = 0;

            public function respond(array $messages, array $tools): array
            {
                $this->turn++;
                return $this->turn === 1
                    ? ['type' => 'tool_call', 'tool' => 'math', 'input' => ['expression' => '2+3']]
                    : ['type' => 'final', 'content' => 'done'];
            }
        };

        $events = [];
        foreach ((new ToolRoutingAgent($model, [new MathTool()]))->chatStream('calculate 2+3') as $event) {
            $events[] = $event->type;
        }

        self::assertContains('run_start', $events);
        self::assertContains('iteration_start', $events);
        self::assertContains('decision', $events);
        self::assertContains('tool_start', $events);
        self::assertContains('tool_result', $events);
        self::assertContains('reflect', $events);
        self::assertSame('final', $events[array_key_last($events)]);
    }

    public function testStreamingModelEmitsTokenEvents(): void
    {
        $model = new class () implements StreamingToolRoutingModelInterface {
            public function respond(array $messages, array $tools): array
            {
                return ['type' => 'final', 'content' => 'streamed'];
            }

            public function streamRespond(array $messages, array $tools): iterable
            {
                yield '{"type":"final","content":"streamed"}';
            }
        };

        $tokens = [];
        foreach ((new ToolRoutingAgent($model, [new MathTool()]))->chatStream('hello') as $event) {
            if ($event->type === 'token') {
                $tokens[] = $event->data['token'] ?? '';
            }
        }

        self::assertNotEmpty($tokens);
    }

    public function testPauseForApprovalAndResumeApproved(): void
    {
        $model = new class () implements ToolRoutingModelInterface {
            private int $turn = 0;

            public function respond(array $messages, array $tools): array
            {
                $this->turn++;
                return $this->turn === 1
                    ? ['type' => 'tool_call', 'tool' => 'risky', 'input' => ['payload' => 'ok']]
                    : ['type' => 'final', 'content' => 'done'];
            }
        };

        $tool = new class () implements ToolInterface, ToolSchemaInterface {
            public function name(): string { return 'risky'; }
            public function description(): string { return 'Risky test tool.'; }
            public function invoke(array $input): string { return 'approved-run'; }
            public function inputSchema(): array { return ['type' => 'object', 'required' => ['payload'], 'properties' => ['payload' => ['type' => 'string']]]; }
            public function examples(): array { return [['payload' => 'ok']]; }
            public function riskLevel(): string { return 'high'; }
        };

        $policy = new AgentPolicy(pauseForApproval: true);
        $agent = new ToolRoutingAgent($model, [$tool], toolExecutor: new ToolExecutor(new ToolInputValidator(), $policy));

        $paused = $agent->chat('run risky');
        self::assertSame('awaiting_approval', $paused['stop_reason']);
        self::assertArrayHasKey('approval_token', $paused);
        self::assertNotNull($paused['pending_approval'] ?? null);

        $resumed = $agent->resumeWithApproval(
            AgentState::fromArray($paused['state']),
            true,
            (string) $paused['approval_token']
        );

        self::assertSame('done', $resumed['answer']);
        self::assertTrue($resumed['structured_tool_calls'][0]['ok'] ?? false);
    }

    public function testPauseForApprovalAndResumeDenied(): void
    {
        $model = new class () implements ToolRoutingModelInterface {
            private int $turn = 0;

            public function respond(array $messages, array $tools): array
            {
                $this->turn++;
                return $this->turn === 1
                    ? ['type' => 'tool_call', 'tool' => 'risky', 'input' => ['payload' => 'ok']]
                    : ['type' => 'final', 'content' => 'continued after denial'];
            }
        };

        $tool = new class () implements ToolInterface, ToolSchemaInterface {
            public function name(): string { return 'risky'; }
            public function description(): string { return 'Risky test tool.'; }
            public function invoke(array $input): string { return 'should-not-run'; }
            public function inputSchema(): array { return ['type' => 'object', 'required' => ['payload'], 'properties' => ['payload' => ['type' => 'string']]]; }
            public function examples(): array { return [['payload' => 'ok']]; }
            public function riskLevel(): string { return 'high'; }
        };

        $policy = new AgentPolicy(pauseForApproval: true);
        $agent = new ToolRoutingAgent($model, [$tool], toolExecutor: new ToolExecutor(new ToolInputValidator(), $policy));
        $paused = $agent->chat('run risky');

        $resumed = $agent->resumeWithApproval(
            AgentState::fromArray($paused['state']),
            false,
            (string) $paused['approval_token']
        );

        self::assertSame('continued after denial', $resumed['answer']);
        self::assertSame([], $resumed['tool_calls']);
    }

    public function testStreamEventSerializesToArray(): void
    {
        $event = new AgentStreamEvent('decision', ['type' => 'final']);
        self::assertSame('decision', $event->toArray()['type']);
    }
}
