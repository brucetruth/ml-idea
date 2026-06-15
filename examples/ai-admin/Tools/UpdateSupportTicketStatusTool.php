<?php

declare(strict_types=1);

namespace ML\IDEA\Examples\AiAdmin\Tools;

use ML\IDEA\Examples\AiAdmin\Support\AdminStore;
use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Contracts\ToolSchemaInterface;

/** Medium risk — auto-runs when only `high` requires HITL (e.g. role change to editor). */
final class UpdateSupportTicketStatusTool implements ToolInterface, ToolSchemaInterface
{
    public function __construct(private readonly AdminStore $store)
    {
    }

    public function name(): string
    {
        return 'update_support_ticket_status';
    }

    public function description(): string
    {
        return 'Update support ticket status (open, pending, resolved, closed).';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['ticket_id', 'status'],
            'properties' => [
                'ticket_id' => ['type' => 'integer', 'minimum' => 1],
                'status' => ['type' => 'string', 'enum' => ['open', 'pending', 'resolved', 'closed']],
            ],
        ];
    }

    public function examples(): array
    {
        return [['ticket_id' => 501, 'status' => 'pending']];
    }

    public function riskLevel(): string
    {
        return 'medium';
    }

    public function invoke(array $input): string
    {
        $ticketId = (int) ($input['ticket_id'] ?? 0);
        $status = (string) ($input['status'] ?? '');
        $ticket = $this->store->updateSupportTicketStatus($ticketId, $status);
        if ($ticket === null) {
            return json_encode(['ok' => false, 'error' => sprintf('Ticket #%d not found.', $ticketId)], JSON_THROW_ON_ERROR);
        }

        return json_encode(['ok' => true, 'message' => sprintf('Ticket #%d → %s.', $ticketId, $status), 'ticket' => $ticket], JSON_THROW_ON_ERROR);
    }
}
