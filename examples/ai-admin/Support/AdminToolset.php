<?php

declare(strict_types=1);

namespace ML\IDEA\Examples\AiAdmin\Support;

use ML\IDEA\Examples\AiAdmin\Tools\AddUserNoteTool;
use ML\IDEA\Examples\AiAdmin\Tools\BanUserTool;
use ML\IDEA\Examples\AiAdmin\Tools\GetUserTool;
use ML\IDEA\Examples\AiAdmin\Tools\ListOrdersTool;
use ML\IDEA\Examples\AiAdmin\Tools\ListUsersTool;
use ML\IDEA\Examples\AiAdmin\Tools\RefundOrderTool;
use ML\IDEA\Examples\AiAdmin\Tools\TagOrderTool;
use ML\IDEA\Examples\AiAdmin\Tools\UpdateSupportTicketStatusTool;
use ML\IDEA\Examples\AiAdmin\Tools\UpdateUserRoleTool;
use ML\IDEA\RAG\Contracts\ToolInterface;

final class AdminToolset
{
    /** @return array<int, ToolInterface> */
    public static function make(?AdminStore $store = null): array
    {
        $store ??= new AdminStore();

        return [
            new ListUsersTool($store),
            new GetUserTool($store),
            new UpdateUserRoleTool($store),
            new BanUserTool($store),
            new ListOrdersTool($store),
            new RefundOrderTool($store),
            new AddUserNoteTool($store),
            new TagOrderTool($store),
            new UpdateSupportTicketStatusTool($store),
        ];
    }

    /**
     * Read + low/medium write tools only — no ban/refund (full AI admin without HITL).
     *
     * @return array<int, ToolInterface>
     */
    public static function makeAutonomous(?AdminStore $store = null): array
    {
        $store ??= new AdminStore();

        return [
            new ListUsersTool($store),
            new GetUserTool($store),
            new ListOrdersTool($store),
            new UpdateUserRoleTool($store),
            new AddUserNoteTool($store),
            new TagOrderTool($store),
            new UpdateSupportTicketStatusTool($store),
        ];
    }
}
