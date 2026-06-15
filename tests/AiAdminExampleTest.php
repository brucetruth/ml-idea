<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Examples\AiAdmin\Support\AdminHeuristicRouter;
use ML\IDEA\Examples\AiAdmin\Support\AdminToolset;
use ML\IDEA\RAG\Agents\ToolRoutingAgent;
use PHPUnit\Framework\TestCase;

final class AiAdminExampleTest extends TestCase
{
    public function testAdminAgentListsUsers(): void
    {
        $agent = new ToolRoutingAgent(new AdminHeuristicRouter(), AdminToolset::make());
        $result = $agent->chat('List all users');

        self::assertSame('final', $result['stop_reason']);
        self::assertStringContainsString('3', $result['answer']);
        self::assertGreaterThanOrEqual(1, count($result['tool_calls']));
    }

    public function testAdminAgentListsOrdersForUser(): void
    {
        $agent = new ToolRoutingAgent(new AdminHeuristicRouter(), AdminToolset::make());
        $result = $agent->chat('Show orders for user #2');

        self::assertSame('final', $result['stop_reason']);
        self::assertStringContainsString('2', $result['answer']);
    }
}
