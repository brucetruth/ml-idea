<?php

declare(strict_types=1);

namespace ML\IDEA\Examples\AiAdmin\Tools;

use ML\IDEA\Examples\AiAdmin\Support\AdminStore;
use ML\IDEA\RAG\Contracts\IdempotentToolInterface;
use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Contracts\ToolSchemaInterface;

final class RefundOrderTool implements ToolInterface, ToolSchemaInterface, IdempotentToolInterface
{
    public function __construct(private readonly AdminStore $store)
    {
    }

    public function name(): string
    {
        return 'refund_order';
    }

    public function description(): string
    {
        return 'Refund an order by id. Requires human approval when HITL is enabled.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['order_id', 'reason'],
            'properties' => [
                'order_id' => ['type' => 'integer', 'minimum' => 1],
                'reason' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 500],
            ],
        ];
    }

    public function examples(): array
    {
        return [['order_id' => 101, 'reason' => 'customer support request']];
    }

    public function riskLevel(): string
    {
        return 'high';
    }

    public function idempotencyKey(array $input): string
    {
        return 'order:' . (int) ($input['order_id'] ?? 0);
    }

    public function invoke(array $input): string
    {
        $orderId = (int) ($input['order_id'] ?? 0);
        $reason = trim((string) ($input['reason'] ?? ''));
        $order = $this->store->refundOrder($orderId, $reason);
        if ($order === null) {
            return json_encode(['ok' => false, 'error' => sprintf('Order #%d not found.', $orderId)], JSON_THROW_ON_ERROR);
        }

        return json_encode([
            'ok' => true,
            'message' => sprintf('Refunded order #%d.', $orderId),
            'order' => $order,
        ], JSON_THROW_ON_ERROR);
    }
}
