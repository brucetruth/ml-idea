<?php

declare(strict_types=1);

namespace ML\IDEA\Examples\AiAdmin\Tools;

use ML\IDEA\Examples\AiAdmin\Support\AdminStore;
use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Contracts\ToolSchemaInterface;

final class ListUsersTool implements ToolInterface, ToolSchemaInterface
{
    public function __construct(private readonly AdminStore $store)
    {
    }

    public function name(): string
    {
        return 'list_users';
    }

    public function description(): string
    {
        return 'List application users. Optional filters: role, status.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'role' => ['type' => 'string', 'enum' => ['admin', 'editor', 'customer']],
                'status' => ['type' => 'string', 'enum' => ['active', 'banned']],
            ],
        ];
    }

    public function examples(): array
    {
        return [['role' => 'customer'], []];
    }

    public function riskLevel(): string
    {
        return 'low';
    }

    public function invoke(array $input): string
    {
        $users = $this->store->listUsers(
            isset($input['role']) ? (string) $input['role'] : null,
            isset($input['status']) ? (string) $input['status'] : null,
        );

        return json_encode(['users' => $users, 'count' => count($users)], JSON_THROW_ON_ERROR);
    }
}
