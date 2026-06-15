<?php

declare(strict_types=1);

namespace ML\IDEA\Examples\AiAdmin\Tools;

use ML\IDEA\Examples\AiAdmin\Support\AdminStore;
use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Contracts\ToolSchemaInterface;

final class UpdateUserRoleTool implements ToolInterface, ToolSchemaInterface
{
    public function __construct(private readonly AdminStore $store)
    {
    }

    public function name(): string
    {
        return 'update_user_role';
    }

    public function description(): string
    {
        return 'Change a user role (admin, editor, customer).';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['user_id', 'role'],
            'properties' => [
                'user_id' => ['type' => 'integer', 'minimum' => 1],
                'role' => ['type' => 'string', 'enum' => ['admin', 'editor', 'customer']],
            ],
        ];
    }

    public function examples(): array
    {
        return [['user_id' => 3, 'role' => 'editor']];
    }

    public function riskLevel(): string
    {
        return 'medium';
    }

    public function invoke(array $input): string
    {
        $userId = (int) ($input['user_id'] ?? 0);
        $role = (string) ($input['role'] ?? '');
        $user = $this->store->updateUserRole($userId, $role);
        if ($user === null) {
            return json_encode(['ok' => false, 'error' => sprintf('User #%d not found.', $userId)], JSON_THROW_ON_ERROR);
        }

        return json_encode([
            'ok' => true,
            'message' => sprintf('Updated user #%d role to %s.', $userId, $role),
            'user' => $user,
        ], JSON_THROW_ON_ERROR);
    }
}
