<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\RAG\Agents\ToolRoutingAgent;
use ML\IDEA\RAG\Contracts\ToolRoutingModelInterface;
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
}
