<?php

declare(strict_types=1);

namespace ML\IDEA\Examples\AiAdmin\Tools;

use ML\IDEA\Examples\AiAdmin\Support\AdminStore;
use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Contracts\ToolSchemaInterface;

final class GetUserTool implements ToolInterface, ToolSchemaInterface
{
    public function __construct(private readonly AdminStore $store)
    {
    }

    public function name(): string
    {
        return 'get_user';
    }

    public function description(): string
    {
        return 'Fetch one user by numeric id.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['user_id'],
            'properties' => [
                'user_id' => ['type' => 'integer', 'minimum' => 1],
            ],
        ];
    }

    public function examples(): array
    {
        return [['user_id' => 2]];
    }

    public function riskLevel(): string
    {
        return 'low';
    }

    public function invoke(array $input): string
    {
        $userId = (int) ($input['user_id'] ?? 0);
        $user = $this->store->getUser($userId);
        if ($user === null) {
            return json_encode(['ok' => false, 'error' => sprintf('User #%d not found.', $userId)], JSON_THROW_ON_ERROR);
        }

        return json_encode(['ok' => true, 'user' => $user], JSON_THROW_ON_ERROR);
    }
}
