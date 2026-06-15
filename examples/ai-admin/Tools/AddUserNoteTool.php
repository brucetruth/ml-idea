<?php

declare(strict_types=1);

namespace ML\IDEA\Examples\AiAdmin\Tools;

use ML\IDEA\Examples\AiAdmin\Support\AdminStore;
use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Contracts\ToolSchemaInterface;

/** Low risk — runs without human approval (writes internal admin note). */
final class AddUserNoteTool implements ToolInterface, ToolSchemaInterface
{
    public function __construct(private readonly AdminStore $store)
    {
    }

    public function name(): string
    {
        return 'add_user_note';
    }

    public function description(): string
    {
        return 'Append an internal admin note on a user record (audit trail, not visible to customer).';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['user_id', 'note'],
            'properties' => [
                'user_id' => ['type' => 'integer', 'minimum' => 1],
                'note' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 2000],
            ],
        ];
    }

    public function examples(): array
    {
        return [['user_id' => 2, 'note' => 'Billing review opened by agent.']];
    }

    public function riskLevel(): string
    {
        return 'low';
    }

    public function invoke(array $input): string
    {
        $userId = (int) ($input['user_id'] ?? 0);
        $note = trim((string) ($input['note'] ?? ''));
        $result = $this->store->addUserNote($userId, $note, 'agent');
        if ($result === null) {
            return json_encode(['ok' => false, 'error' => sprintf('User #%d not found.', $userId)], JSON_THROW_ON_ERROR);
        }

        return json_encode(['ok' => true, 'message' => 'Note saved.', ...$result], JSON_THROW_ON_ERROR);
    }
}
