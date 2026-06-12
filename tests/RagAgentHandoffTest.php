<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\RAG\Agents\AgentDecision;
use ML\IDEA\RAG\Agents\AgentHandoffRegistry;
use ML\IDEA\RAG\Agents\ToolRoutingAgent;
use ML\IDEA\RAG\Contracts\ToolRoutingModelInterface;
use ML\IDEA\RAG\Tools\MathTool;
use PHPUnit\Framework\TestCase;

final class RagAgentHandoffTest extends TestCase
{
    public function testHandoffDecisionRoutesToSpecialistAndSynthesizesFinalAnswer(): void
    {
        $specialistModel = new class () implements ToolRoutingModelInterface {
            public function respond(array $messages, array $tools): array
            {
                return ['type' => 'final', 'content' => 'specialist answer: 42'];
            }
        };

        $supervisorModel = new class () implements ToolRoutingModelInterface {
            private int $turn = 0;

            public function respond(array $messages, array $tools): array
            {
                $this->turn++;
                if ($this->turn === 1) {
                    return ['type' => 'handoff', 'agent' => 'math_expert', 'content' => 'compute 6*7'];
                }

                return ['type' => 'final', 'content' => 'The expert says 42'];
            }
        };

        $registry = new AgentHandoffRegistry();
        $specialist = new ToolRoutingAgent($specialistModel, [new MathTool()], agentName: 'MathExpert');
        $registry->register('math_expert', $specialist, 'Handles arithmetic questions');

        $supervisor = new ToolRoutingAgent($supervisorModel, [], handoffRegistry: $registry);
        $result = $supervisor->chat('what is 6*7');

        self::assertSame('The expert says 42', $result['answer']);
        self::assertCount(1, $result['handoffs']);
        self::assertSame('math_expert', $result['handoffs'][0]['agent']);
        self::assertSame('compute 6*7', $result['handoffs'][0]['task']);
        self::assertSame('specialist answer: 42', $result['handoffs'][0]['answer']);
        self::assertSame('handoff', $result['decisions'][0]['type']);
        self::assertSame('math_expert', $result['decisions'][0]['handoff_target']);
    }

    public function testChatStreamEmitsHandoffEvents(): void
    {
        $specialistModel = new class () implements ToolRoutingModelInterface {
            public function respond(array $messages, array $tools): array
            {
                return ['type' => 'final', 'content' => 'done from specialist'];
            }
        };

        $supervisorModel = new class () implements ToolRoutingModelInterface {
            private int $turn = 0;

            public function respond(array $messages, array $tools): array
            {
                $this->turn++;
                return $this->turn === 1
                    ? ['type' => 'handoff', 'agent' => 'worker', 'content' => 'do task']
                    : ['type' => 'final', 'content' => 'all done'];
            }
        };

        $registry = new AgentHandoffRegistry();
        $registry->register('worker', new ToolRoutingAgent($specialistModel, []), 'General worker');

        $events = [];
        foreach ((new ToolRoutingAgent($supervisorModel, [], handoffRegistry: $registry))->chatStream('run task') as $event) {
            $events[] = $event->type;
        }

        self::assertContains('handoff_start', $events);
        self::assertContains('handoff_result', $events);
        self::assertSame('final', $events[array_key_last($events)]);
    }

    public function testUnknownHandoffTargetRecordsErrorAndContinues(): void
    {
        $model = new class () implements ToolRoutingModelInterface {
            private int $turn = 0;

            public function respond(array $messages, array $tools): array
            {
                $this->turn++;
                return $this->turn === 1
                    ? ['type' => 'handoff', 'agent' => 'missing', 'content' => 'help']
                    : ['type' => 'final', 'content' => 'handled locally'];
            }
        };

        $registry = new AgentHandoffRegistry();
        $registry->register('other', new ToolRoutingAgent(new class () implements ToolRoutingModelInterface {
            public function respond(array $messages, array $tools): array
            {
                return ['type' => 'final', 'content' => 'unused'];
            }
        }, []));

        $result = (new ToolRoutingAgent($model, [], handoffRegistry: $registry))->chat('help me');

        self::assertSame('handled locally', $result['answer']);
        self::assertSame([], $result['handoffs']);
    }

    public function testSystemPromptIncludesRegisteredSpecialists(): void
    {
        $registry = new AgentHandoffRegistry();
        $registry->register(
            'researcher',
            new ToolRoutingAgent(new class () implements ToolRoutingModelInterface {
                public function respond(array $messages, array $tools): array
                {
                    return ['type' => 'final', 'content' => 'ok'];
                }
            }, []),
            'Finds facts in documents'
        );

        $prompt = (new ToolRoutingAgent(new class () implements ToolRoutingModelInterface {
            public function respond(array $messages, array $tools): array
            {
                return ['type' => 'final', 'content' => 'ok'];
            }
        }, [], handoffRegistry: $registry))->getSystemPrompt();

        self::assertStringContainsString('researcher', $prompt);
        self::assertStringContainsString('Finds facts in documents', $prompt);
        self::assertStringContainsString('"type":"handoff"', $prompt);
    }

    public function testAgentDecisionParsesHandoffPayload(): void
    {
        $decision = AgentDecision::fromArray([
            'type' => 'handoff',
            'agent' => 'coder',
            'content' => 'implement feature',
            'confidence' => 0.9,
        ]);

        self::assertSame('handoff', $decision->type);
        self::assertSame('coder', $decision->handoffTarget);
        self::assertSame('implement feature', $decision->content);
        self::assertFalse($decision->isTerminal());
    }
}
