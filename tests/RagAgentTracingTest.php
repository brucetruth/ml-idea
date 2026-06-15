<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\RAG\Agents\AgentHandoffRegistry;
use ML\IDEA\RAG\Agents\OpenTelemetryAgentTracer;
use ML\IDEA\RAG\Agents\RecordingAgentTracer;
use ML\IDEA\RAG\Agents\ToolRoutingAgent;
use ML\IDEA\RAG\Agents\TraceRedactor;
use ML\IDEA\RAG\Contracts\ToolRoutingModelInterface;
use ML\IDEA\RAG\Tools\MathTool;
use PHPUnit\Framework\TestCase;

final class RagAgentTracingTest extends TestCase
{
    public function testDefaultTracerDoesNotAttachTelemetry(): void
    {
        $model = new class () implements ToolRoutingModelInterface {
            public function respond(array $messages, array $tools): array
            {
                return ['type' => 'final', 'content' => 'ok'];
            }
        };

        $result = (new ToolRoutingAgent($model, []))->chat('hello');

        self::assertArrayNotHasKey('telemetry', $result);
    }

    public function testRecordingTracerCapturesRunIterationAndToolSpans(): void
    {
        $model = new class () implements ToolRoutingModelInterface {
            private int $turn = 0;

            public function respond(array $messages, array $tools): array
            {
                $this->turn++;
                return $this->turn === 1
                    ? ['type' => 'tool_call', 'tool' => 'math', 'input' => ['expression' => '2+2']]
                    : ['type' => 'final', 'content' => '4'];
            }
        };

        $tracer = new RecordingAgentTracer();
        $result = (new ToolRoutingAgent($model, [new MathTool()], agentTracer: $tracer))->chat('calculate 2+2');

        self::assertSame('4', $result['answer']);
        self::assertArrayHasKey('telemetry', $result);
        self::assertNotSame('', $result['telemetry']['trace_id']);
        self::assertNotSame('', $result['telemetry']['span_id']);

        $names = array_column($tracer->spans(), 'name');
        self::assertContains('agent.run', $names);
        self::assertContains('agent.iteration', $names);
        self::assertContains('agent.tool_call', $names);

        $runSpan = null;
        foreach ($tracer->spans() as $span) {
            if (($span['name'] ?? '') === 'agent.run') {
                $runSpan = $span;
                break;
            }
        }

        self::assertNotNull($runSpan);
        self::assertSame('final', $runSpan['attributes']['agent.stop_reason'] ?? null);
        self::assertSame(2, $runSpan['attributes']['agent.iterations'] ?? null);
    }

    public function testRecordingTracerCapturesHandoffSpan(): void
    {
        $specialistModel = new class () implements ToolRoutingModelInterface {
            public function respond(array $messages, array $tools): array
            {
                return ['type' => 'final', 'content' => 'specialist done'];
            }
        };

        $supervisorModel = new class () implements ToolRoutingModelInterface {
            private int $turn = 0;

            public function respond(array $messages, array $tools): array
            {
                $this->turn++;
                return $this->turn === 1
                    ? ['type' => 'handoff', 'agent' => 'worker', 'content' => 'do task']
                    : ['type' => 'final', 'content' => 'done'];
            }
        };

        $tracer = new RecordingAgentTracer();
        $registry = new AgentHandoffRegistry();
        $registry->register('worker', new ToolRoutingAgent($specialistModel, []), 'Worker');

        (new ToolRoutingAgent($supervisorModel, [], handoffRegistry: $registry, agentTracer: $tracer))->chat('run');

        $names = array_column($tracer->spans(), 'name');
        self::assertContains('agent.handoff', $names);
    }

    public function testTraceAttributesAreRedacted(): void
    {
        $model = new class () implements ToolRoutingModelInterface {
            public function respond(array $messages, array $tools): array
            {
                return ['type' => 'final', 'content' => 'ok'];
            }
        };

        $tracer = new RecordingAgentTracer();
        (new ToolRoutingAgent(
            $model,
            [],
            agentTracer: $tracer,
            traceRedactor: new TraceRedactor(['secret']),
        ))->chat('hello ?secret=shhh');

        $runSpan = null;
        foreach ($tracer->spans() as $span) {
            if (($span['name'] ?? '') === 'agent.run') {
                $runSpan = $span;
                break;
            }
        }

        self::assertNotNull($runSpan);
        self::assertStringNotContainsString('shhh', (string) ($runSpan['attributes']['agent.goal'] ?? ''));
    }

    public function testChatStreamRunStartIncludesTelemetryWhenTracerEnabled(): void
    {
        $model = new class () implements ToolRoutingModelInterface {
            public function respond(array $messages, array $tools): array
            {
                return ['type' => 'final', 'content' => 'ok'];
            }
        };

        $tracer = new RecordingAgentTracer();
        $events = [];
        foreach ((new ToolRoutingAgent($model, [], agentTracer: $tracer))->chatStream('hello') as $event) {
            $events[$event->type] = $event->data;
        }

        self::assertNotSame('', $events['run_start']['telemetry']['trace_id'] ?? '');
    }

    public function testOpenTelemetryTracerRequiresSdk(): void
    {
        if (interface_exists('OpenTelemetry\\API\\Trace\\TracerInterface')) {
            self::markTestSkipped('OpenTelemetry SDK is installed.');
        }

        $this->expectException(\ML\IDEA\Exceptions\InvalidArgumentException::class);
        new OpenTelemetryAgentTracer(new \stdClass());
    }
}
