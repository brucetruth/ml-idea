<?php

declare(strict_types=1);

namespace ML\IDEA\Examples\AiAdmin\Tools;

use ML\IDEA\Examples\AiAdmin\Support\AdminStore;
use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Contracts\ToolSchemaInterface;

final class ListOrdersTool implements ToolInterface, ToolSchemaInterface
{
    public function __construct(private readonly AdminStore $store)
    {
    }

    public function name(): string
    {
        return 'list_orders';
    }

    public function description(): string
    {
        return 'List orders. Optional filters: user_id, status.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'user_id' => ['type' => 'integer', 'minimum' => 1],
                'status' => ['type' => 'string', 'enum' => ['paid', 'shipped', 'refunded']],
            ],
        ];
    }

    public function examples(): array
    {
        return [['user_id' => 2], []];
    }

    public function riskLevel(): string
    {
        return 'low';
    }

    public function invoke(array $input): string
    {
        $orders = $this->store->listOrders(
            isset($input['user_id']) ? (int) $input['user_id'] : null,
            isset($input['status']) ? (string) $input['status'] : null,
        );

        return json_encode(['orders' => $orders, 'count' => count($orders)], JSON_THROW_ON_ERROR);
    }
}
