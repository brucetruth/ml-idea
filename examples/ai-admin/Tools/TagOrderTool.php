<?php

declare(strict_types=1);

namespace ML\IDEA\Examples\AiAdmin\Tools;

use ML\IDEA\Examples\AiAdmin\Support\AdminStore;
use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Contracts\ToolSchemaInterface;

/** Low risk — runs without human approval (internal order tag). */
final class TagOrderTool implements ToolInterface, ToolSchemaInterface
{
    public function __construct(private readonly AdminStore $store)
    {
    }

    public function name(): string
    {
        return 'tag_order';
    }

    public function description(): string
    {
        return 'Add an internal tag to an order (e.g. billing-review, vip, fraud-check).';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['order_id', 'tag'],
            'properties' => [
                'order_id' => ['type' => 'integer', 'minimum' => 1],
                'tag' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 64],
            ],
        ];
    }

    public function examples(): array
    {
        return [['order_id' => 101, 'tag' => 'billing-review']];
    }

    public function riskLevel(): string
    {
        return 'low';
    }

    public function invoke(array $input): string
    {
        $orderId = (int) ($input['order_id'] ?? 0);
        $tag = trim((string) ($input['tag'] ?? ''));
        $result = $this->store->tagOrder($orderId, $tag);
        if ($result === null) {
            return json_encode(['ok' => false, 'error' => sprintf('Order #%d not found.', $orderId)], JSON_THROW_ON_ERROR);
        }

        return json_encode(['ok' => true, 'message' => sprintf('Tagged order #%d.', $orderId), ...$result], JSON_THROW_ON_ERROR);
    }
}
