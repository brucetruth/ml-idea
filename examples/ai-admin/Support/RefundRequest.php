<?php

declare(strict_types=1);

namespace ML\IDEA\Examples\AiAdmin\Support;

/**
 * Customer-submitted refund ticket passed to the agent for triage.
 */
final class RefundRequest
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly int $orderId,
        public readonly string $reason,
        public string $status = 'pending',
        public ?string $agentDecision = null,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromCustomerForm(array $payload): self
    {
        return new self(
            id: (int) ($payload['id'] ?? random_int(1000, 9999)),
            userId: (int) ($payload['user_id'] ?? 0),
            orderId: (int) ($payload['order_id'] ?? 0),
            reason: trim((string) ($payload['reason'] ?? '')),
        );
    }

    /**
     * Prompt for ToolRoutingAgent — the agent investigates and decides approve / deny / escalate.
     */
    public function toAgentMessage(): string
    {
        return implode("\n", [
            sprintf('NEW REFUND REQUEST #%d (status: %s)', $this->id, $this->status),
            sprintf('- customer user_id=%d', $this->userId),
            sprintf('- order_id=%d', $this->orderId),
            sprintf('- reason: %s', $this->reason),
            '',
            'Review this request:',
            '1) Use read-only tools to verify the customer and order.',
            '2) Approve by calling refund_order if policy allows.',
            '3) Deny with a clear final answer if not eligible (do not call refund_order).',
            '4) Escalate to a human if uncertain.',
        ]);
    }
}
