<?php

declare(strict_types=1);

namespace ML\IDEA\Examples\AiAdmin\Tools;

use ML\IDEA\Examples\AiAdmin\Support\AdminStore;
use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Contracts\ToolSchemaInterface;

final class BanUserTool implements ToolInterface, ToolSchemaInterface
{
    public function __construct(private readonly AdminStore $store)
    {
    }

    public function name(): string
    {
        return 'ban_user';
    }

    public function description(): string
    {
        return 'Ban a user account. Requires human approval when HITL is enabled.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['user_id', 'reason'],
            'properties' => [
                'user_id' => ['type' => 'integer', 'minimum' => 1],
                'reason' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 500],
            ],
        ];
    }

    public function examples(): array
    {
        return [['user_id' => 2, 'reason' => 'chargeback abuse']];
    }

    public function riskLevel(): string
    {
        return 'high';
    }

    public function invoke(array $input): string
    {
        $userId = (int) ($input['user_id'] ?? 0);
        $reason = trim((string) ($input['reason'] ?? ''));
        $user = $this->store->banUser($userId, $reason);
        if ($user === null) {
            return json_encode(['ok' => false, 'error' => sprintf('User #%d not found.', $userId)], JSON_THROW_ON_ERROR);
        }

        return json_encode([
            'ok' => true,
            'message' => sprintf('Banned user #%d.', $userId),
            'user' => $user,
        ], JSON_THROW_ON_ERROR);
    }
}
