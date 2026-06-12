<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\RAG\Agents\ToolRoutingAgent;
use ML\IDEA\RAG\Agents\AgentPolicy;
use ML\IDEA\RAG\Agents\AgentBudget;
use ML\IDEA\RAG\Agents\AgentState;
use ML\IDEA\RAG\Agents\JsonFileAgentStateStore;
use ML\IDEA\RAG\Agents\ToolExecutor;
use ML\IDEA\RAG\Agents\ToolInputValidator;
use ML\IDEA\RAG\Contracts\ToolRoutingModelInterface;
use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Contracts\ToolSchemaInterface;
use ML\IDEA\RAG\LLM\HeuristicToolRoutingModel;
use ML\IDEA\RAG\Tools\MathTool;
use PHPUnit\Framework\TestCase;

final class RagToolRoutingAgentTest extends TestCase
{
    public function testToolRoutingAgentCanCallToolAndFinalize(): void
    {
        $model = new class () implements ToolRoutingModelInterface {
            private int $turn = 0;

            public function respond(array $messages, array $tools): array
            {
                $this->turn++;

                if ($this->turn === 1) {
                    return ['type' => 'tool_call', 'tool' => 'math', 'input' => ['expression' => '10+5']];
                }

                return ['type' => 'final', 'content' => 'done'];
            }
        };

        $agent = new ToolRoutingAgent($model, [new MathTool()]);
        $result = $agent->chat('calculate 10+5');

        self::assertSame('done', $result['answer']);
        self::assertCount(1, $result['tool_calls']);
        self::assertSame('math', $result['tool_calls'][0]['name']);
    }

    public function testToolRoutingAgentSupportsCustomAgentPromptFields(): void
    {
        $model = new class () implements ToolRoutingModelInterface {
            public function respond(array $messages, array $tools): array
            {
                return ['type' => 'final', 'content' => 'ok'];
            }
        };

        $agent = new ToolRoutingAgent(
            $model,
            [new MathTool()],
            maxIterations: 3,
            agentName: 'OpsRoutingAgent',
            agentFeatures: ['Prefer reliable tools first', 'Keep responses concise']
        );

        $prompt = $agent->getSystemPrompt();
        self::assertStringContainsString('You are OpsRoutingAgent, a tool-using agent.', $prompt);
        self::assertStringContainsString('Prefer reliable tools first', $prompt);
    }

    public function testToolRoutingAgentSupportsMultipleToolCallsAndStructuredResults(): void
    {
        $model = new class () implements ToolRoutingModelInterface {
            private int $turn = 0;

            public function respond(array $messages, array $tools): array
            {
                $this->turn++;
                if ($this->turn === 1) {
                    return [
                        'type' => 'tool_calls',
                        'tool_calls' => [
                            ['tool' => 'math', 'input' => ['expression' => '10+5']],
                            ['tool' => 'math', 'input' => ['expression' => 'sqrt(81)']],
                        ],
                    ];
                }

                return ['type' => 'final', 'content' => 'done'];
            }
        };

        $agent = new ToolRoutingAgent($model, [new MathTool()]);
        $result = $agent->chat('calculate two things');

        self::assertSame('done', $result['answer']);
        self::assertSame('final', $result['stop_reason']);
        self::assertCount(2, $result['tool_calls']);
        self::assertCount(2, $result['structured_tool_calls']);
        self::assertTrue($result['structured_tool_calls'][0]['ok']);
    }

    public function testToolRoutingAgentValidatesToolInputBeforeInvocation(): void
    {
        $model = new class () implements ToolRoutingModelInterface {
            private int $turn = 0;

            public function respond(array $messages, array $tools): array
            {
                $this->turn++;
                if ($this->turn === 1) {
                    return ['type' => 'tool_call', 'tool' => 'math', 'input' => []];
                }

                return ['type' => 'final', 'content' => 'validation observed'];
            }
        };

        $agent = new ToolRoutingAgent($model, [new MathTool()]);
        $result = $agent->chat('calculate');

        self::assertSame('validation observed', $result['answer']);
        self::assertFalse($result['structured_tool_calls'][0]['ok']);
        self::assertSame('validation_error', $result['structured_tool_calls'][0]['error_type']);
        self::assertStringContainsString('Missing required field: expression', $result['tool_calls'][0]['output']);
    }

    public function testToolRoutingAgentCapturesToolExceptions(): void
    {
        $model = new class () implements ToolRoutingModelInterface {
            private int $turn = 0;

            public function respond(array $messages, array $tools): array
            {
                $this->turn++;
                if ($this->turn === 1) {
                    return ['type' => 'tool_call', 'tool' => 'boom', 'input' => []];
                }

                return ['type' => 'final', 'content' => 'exception observed'];
            }
        };

        $tool = new class () implements ToolInterface {
            public function name(): string
            {
                return 'boom';
            }

            public function description(): string
            {
                return 'Throws for tests.';
            }

            public function invoke(array $input): string
            {
                throw new \RuntimeException('boom failed');
            }
        };

        $agent = new ToolRoutingAgent($model, [$tool]);
        $result = $agent->chat('run boom');

        self::assertSame('exception observed', $result['answer']);
        self::assertFalse($result['structured_tool_calls'][0]['ok']);
        self::assertSame('tool_exception', $result['structured_tool_calls'][0]['error_type']);
    }

    public function testToolRoutingAgentSupportsClarifyAndRefuseDecisions(): void
    {
        $clarifyModel = new class () implements ToolRoutingModelInterface {
            public function respond(array $messages, array $tools): array
            {
                return ['type' => 'clarify', 'content' => 'Which location?'];
            }
        };

        $clarify = (new ToolRoutingAgent($clarifyModel, [new MathTool()]))->chat('weather there');
        self::assertSame('clarify', $clarify['stop_reason']);
        self::assertSame('Which location?', $clarify['answer']);

        $refuseModel = new class () implements ToolRoutingModelInterface {
            public function respond(array $messages, array $tools): array
            {
                return ['type' => 'refuse', 'content' => 'Unsafe request.'];
            }
        };

        $refuse = (new ToolRoutingAgent($refuseModel, [new MathTool()]))->chat('unsafe');
        self::assertSame('refuse', $refuse['stop_reason']);
        self::assertSame('Unsafe request.', $refuse['answer']);
    }

    public function testToolRoutingAgentRejectsDuplicateToolNames(): void
    {
        $model = new class () implements ToolRoutingModelInterface {
            public function respond(array $messages, array $tools): array
            {
                return ['type' => 'final', 'content' => 'ok'];
            }
        };

        $this->expectException(\InvalidArgumentException::class);
        new ToolRoutingAgent($model, [new MathTool(), new MathTool()]);
    }

    public function testToolRoutingAgentReturnsDecisionAndEventState(): void
    {
        $model = new class () implements ToolRoutingModelInterface {
            private int $turn = 0;

            public function respond(array $messages, array $tools): array
            {
                $this->turn++;
                if ($this->turn === 1) {
                    return ['type' => 'plan', 'content' => 'Calculate first.'];
                }
                if ($this->turn === 2) {
                    return ['type' => 'tool_call', 'tool' => 'math', 'input' => ['expression' => '2+2']];
                }

                return ['type' => 'final', 'content' => 'finished'];
            }
        };

        $result = (new ToolRoutingAgent($model, [new MathTool()]))->chat('plan and calculate');

        self::assertSame('finished', $result['answer']);
        self::assertCount(3, $result['decisions']);
        self::assertSame('plan', $result['decisions'][0]['type']);
        self::assertContains('tool_call', array_column($result['events'], 'type'));
    }

    public function testToolExecutorRedactsSensitiveTraceValues(): void
    {
        $model = new class () implements ToolRoutingModelInterface {
            private int $turn = 0;

            public function respond(array $messages, array $tools): array
            {
                $this->turn++;
                if ($this->turn === 1) {
                    return ['type' => 'tool_call', 'tool' => 'echo_secret', 'input' => ['api_key' => 'secret-123', 'query' => 'ok']];
                }

                return ['type' => 'final', 'content' => 'redacted'];
            }
        };

        $tool = new class () implements ToolInterface {
            public function name(): string
            {
                return 'echo_secret';
            }

            public function description(): string
            {
                return 'Echoes input.';
            }

            public function invoke(array $input): string
            {
                return json_encode($input, JSON_THROW_ON_ERROR);
            }
        };

        $result = (new ToolRoutingAgent($model, [$tool]))->chat('echo secret');

        self::assertSame('[redacted]', $result['structured_tool_calls'][0]['input']['api_key']);
        self::assertStringNotContainsString('secret-123', $result['structured_tool_calls'][0]['output']);
    }

    public function testHighRiskToolRequiresConfirmationButCanBeApproved(): void
    {
        $model = new class () implements ToolRoutingModelInterface {
            private int $turn = 0;

            public function respond(array $messages, array $tools): array
            {
                $this->turn++;
                if ($this->turn === 1) {
                    return ['type' => 'tool_call', 'tool' => 'risky', 'input' => ['payload' => 'ok']];
                }

                return ['type' => 'final', 'content' => 'done'];
            }
        };

        $tool = new class () implements ToolInterface, ToolSchemaInterface {
            public function name(): string
            {
                return 'risky';
            }

            public function description(): string
            {
                return 'Risky test tool.';
            }

            public function invoke(array $input): string
            {
                return 'approved';
            }

            public function inputSchema(): array
            {
                return ['type' => 'object', 'required' => ['payload'], 'properties' => ['payload' => ['type' => 'string']]];
            }

            public function examples(): array
            {
                return [['payload' => 'ok']];
            }

            public function riskLevel(): string
            {
                return 'high';
            }
        };

        $blocked = (new ToolRoutingAgent($model, [$tool]))->chat('run risky');
        self::assertSame('policy_block', $blocked['structured_tool_calls'][0]['error_type']);

        $approvingModel = new class () implements ToolRoutingModelInterface {
            private int $turn = 0;

            public function respond(array $messages, array $tools): array
            {
                $this->turn++;
                return $this->turn === 1
                    ? ['type' => 'tool_call', 'tool' => 'risky', 'input' => ['payload' => 'ok']]
                    : ['type' => 'final', 'content' => 'done'];
            }
        };
        $policy = new AgentPolicy(confirmationCallback: static fn (string $toolName, string $riskLevel, array $input): bool => $toolName === 'risky' && $riskLevel === 'high');
        $executor = new ToolExecutor(new ToolInputValidator(), $policy);
        $approved = (new ToolRoutingAgent($approvingModel, [$tool], toolExecutor: $executor))->chat('run risky');
        self::assertTrue($approved['structured_tool_calls'][0]['ok']);
    }

    public function testHeuristicRouterClarifiesWeatherWithoutCoordinatesAndHandlesMultiIntent(): void
    {
        $router = new HeuristicToolRoutingModel();
        $agent = new ToolRoutingAgent($router, [new MathTool(), new class () implements ToolInterface, ToolSchemaInterface {
            public function name(): string { return 'weather'; }
            public function description(): string { return 'Weather stub.'; }
            public function invoke(array $input): string { return json_encode(['weather' => $input], JSON_THROW_ON_ERROR); }
            public function inputSchema(): array { return ['type' => 'object', 'required' => ['lat', 'lon'], 'properties' => ['lat' => ['type' => 'number'], 'lon' => ['type' => 'number']]]; }
            public function examples(): array { return [['lat' => -15.3, 'lon' => 28.3]]; }
            public function riskLevel(): string { return 'low'; }
        }]);

        $clarify = $agent->chat('what is the weather there');
        self::assertSame('clarify', $clarify['stop_reason']);

        $multi = $agent->chat('what is 2+2 and weather lat -15.3 lon 28.3');
        self::assertCount(2, $multi['tool_calls']);
    }

    public function testAgentPlannerRecordsPlanObserveReflectEvents(): void
    {
        $model = new class () implements ToolRoutingModelInterface {
            private int $turn = 0;

            public function respond(array $messages, array $tools): array
            {
                $this->turn++;
                return match ($this->turn) {
                    1 => ['type' => 'plan', 'content' => 'Use math.'],
                    2 => ['type' => 'tool_call', 'tool' => 'math', 'input' => ['expression' => '3+4']],
                    default => ['type' => 'final', 'content' => 'done'],
                };
            }
        };

        $result = (new ToolRoutingAgent($model, [new MathTool()]))->chat('plan 3+4');
        $eventTypes = array_column($result['events'], 'type');

        self::assertContains('plan', $eventTypes);
        self::assertContains('observe', $eventTypes);
        self::assertContains('reflect', $eventTypes);
        self::assertArrayHasKey('state', $result);
        self::assertArrayHasKey('budget', $result);
    }

    public function testAgentStateCanSerializeAndResume(): void
    {
        $model = new class () implements ToolRoutingModelInterface {
            public function respond(array $messages, array $tools): array
            {
                return ['type' => 'final', 'content' => 'resumed with ' . count($messages) . ' messages'];
            }
        };

        $state = new AgentState('resume goal', 'system prompt');
        $state->addMessage('assistant', 'prior context');
        $json = $state->toJson();
        $resumed = AgentState::fromJson($json);

        $result = (new ToolRoutingAgent($model, [new MathTool()]))->chatWithState($resumed);

        self::assertSame('resume goal', $result['state']['goal']);
        self::assertSame('resumed with 3 messages', $result['answer']);
    }

    public function testAgentBudgetStopsToolCalls(): void
    {
        $model = new class () implements ToolRoutingModelInterface {
            public function respond(array $messages, array $tools): array
            {
                return ['type' => 'tool_call', 'tool' => 'math', 'input' => ['expression' => '1+1']];
            }
        };

        $agent = new ToolRoutingAgent($model, [new MathTool()], budget: new AgentBudget(maxIterations: 5, maxToolCalls: 1));
        $result = $agent->chat('keep calculating');

        self::assertSame('tool_budget_exceeded', $result['stop_reason']);
        self::assertSame(1, $result['budget']['tool_calls_used']);
    }

    public function testJsonFileAgentStateStorePersistsLoadsAndDeletesState(): void
    {
        $dir = sys_get_temp_dir() . '/ml_idea_agent_state_' . uniqid('', true);
        $store = new JsonFileAgentStateStore($dir);
        $state = new AgentState('persist goal', 'system prompt');
        $state->addMessage('assistant', 'saved context');

        $store->save('session/unsafe id', $state);
        self::assertTrue($store->exists('session/unsafe id'));

        $loaded = $store->load('session/unsafe id');
        self::assertInstanceOf(AgentState::class, $loaded);
        self::assertSame('persist goal', $loaded->goal);
        self::assertCount(3, $loaded->messages());

        $store->delete('session/unsafe id');
        self::assertFalse($store->exists('session/unsafe id'));

        @rmdir($dir);
    }
}
