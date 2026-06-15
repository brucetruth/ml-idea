<?php

declare(strict_types=1);

namespace ML\IDEA\Examples\AiAdmin\Support;

/**
 * In-memory admin backend for demos.
 * Replace with Eloquent repositories in a real Laravel app.
 */
final class AdminStore
{
    /** @var array<int, array{id: int, name: string, email: string, role: string, status: string}> */
    private array $users = [
        1 => ['id' => 1, 'name' => 'Ada Admin', 'email' => 'ada@example.com', 'role' => 'admin', 'status' => 'active'],
        2 => ['id' => 2, 'name' => 'Bob Buyer', 'email' => 'bob@example.com', 'role' => 'customer', 'status' => 'active'],
        3 => ['id' => 3, 'name' => 'Cara Creator', 'email' => 'cara@example.com', 'role' => 'editor', 'status' => 'active'],
    ];

    /** @var array<int, array{id: int, user_id: int, total: float, status: string, sku: string}> */
    private array $orders = [
        101 => ['id' => 101, 'user_id' => 2, 'total' => 49.99, 'status' => 'paid', 'sku' => 'PLAN-PRO'],
        102 => ['id' => 102, 'user_id' => 2, 'total' => 12.00, 'status' => 'shipped', 'sku' => 'ADDON-SEATS'],
        103 => ['id' => 103, 'user_id' => 3, 'total' => 9.99, 'status' => 'paid', 'sku' => 'PLAN-BASIC'],
    ];

    /** @var array<int, RefundRequest> */
    private array $refundRequests = [];

    /** @var array<int, array<int, array{note: string, source: string, at: string}>> */
    private array $userNotes = [];

    /** @var array<int, array<int, string>> */
    private array $orderTags = [];

    /** @var array<int, array{id: int, user_id: int, subject: string, status: string, body: string}> */
    private array $supportTickets = [
        501 => [
            'id' => 501,
            'user_id' => 2,
            'subject' => 'Invoice looks wrong',
            'status' => 'open',
            'body' => 'I think order #101 was billed incorrectly.',
        ],
    ];

    public function submitRefundRequest(RefundRequest $request): RefundRequest
    {
        $this->refundRequests[$request->id] = $request;

        return $request;
    }

    public function getRefundRequest(int $id): ?RefundRequest
    {
        return $this->refundRequests[$id] ?? null;
    }

    /** @return array<int, RefundRequest> */
    public function pendingRefundRequests(): array
    {
        return array_values(array_filter(
            $this->refundRequests,
            static fn (RefundRequest $request): bool => $request->status === 'pending'
        ));
    }

    public function markRefundRequest(string $status, int $id, ?string $agentDecision = null): void
    {
        if (!isset($this->refundRequests[$id])) {
            return;
        }

        $this->refundRequests[$id]->status = $status;
        $this->refundRequests[$id]->agentDecision = $agentDecision;
    }

    /** @return array<int, array<string, mixed>> */
    public function listUsers(?string $role = null, ?string $status = null): array
    {
        return array_values(array_filter(
            $this->users,
            static function (array $user) use ($role, $status): bool {
                if ($role !== null && $role !== '' && $user['role'] !== $role) {
                    return false;
                }
                if ($status !== null && $status !== '' && $user['status'] !== $status) {
                    return false;
                }

                return true;
            }
        ));
    }

    /** @return array<string, mixed>|null */
    public function getUser(int $userId): ?array
    {
        return $this->users[$userId] ?? null;
    }

    /** @return array<string, mixed>|null */
    public function updateUserRole(int $userId, string $role): ?array
    {
        if (!isset($this->users[$userId])) {
            return null;
        }

        $this->users[$userId]['role'] = $role;

        return $this->users[$userId];
    }

    /** @return array<string, mixed>|null */
    public function banUser(int $userId, string $reason): ?array
    {
        if (!isset($this->users[$userId])) {
            return null;
        }

        $this->users[$userId]['status'] = 'banned';
        $this->users[$userId]['ban_reason'] = $reason;

        return $this->users[$userId];
    }

    /** @return array<int, array<string, mixed>> */
    public function listOrders(?int $userId = null, ?string $status = null): array
    {
        return array_values(array_filter(
            $this->orders,
            static function (array $order) use ($userId, $status): bool {
                if ($userId !== null && $userId > 0 && $order['user_id'] !== $userId) {
                    return false;
                }
                if ($status !== null && $status !== '' && $order['status'] !== $status) {
                    return false;
                }

                return true;
            }
        ));
    }

    /** @return array<string, mixed>|null */
    public function refundOrder(int $orderId, string $reason): ?array
    {
        if (!isset($this->orders[$orderId])) {
            return null;
        }

        $this->orders[$orderId]['status'] = 'refunded';
        $this->orders[$orderId]['refund_reason'] = $reason;

        return $this->orders[$orderId];
    }

    /** @return array<string, mixed>|null */
    public function addUserNote(int $userId, string $note, string $source = 'agent'): ?array
    {
        if (!isset($this->users[$userId])) {
            return null;
        }

        $this->userNotes[$userId] ??= [];
        $this->userNotes[$userId][] = [
            'note' => trim($note),
            'source' => $source,
            'at' => date('c'),
        ];

        return ['user_id' => $userId, 'notes' => $this->userNotes[$userId]];
    }

    /** @return array<int, array{note: string, source: string, at: string}> */
    public function userNotes(int $userId): array
    {
        return $this->userNotes[$userId] ?? [];
    }

    /** @return array<string, mixed>|null */
    public function tagOrder(int $orderId, string $tag): ?array
    {
        if (!isset($this->orders[$orderId])) {
            return null;
        }

        $tag = trim($tag);
        $this->orderTags[$orderId] ??= [];
        if (!in_array($tag, $this->orderTags[$orderId], true)) {
            $this->orderTags[$orderId][] = $tag;
        }

        return ['order_id' => $orderId, 'tags' => $this->orderTags[$orderId]];
    }

    /** @return array<int, string> */
    public function orderTags(int $orderId): array
    {
        return $this->orderTags[$orderId] ?? [];
    }

    /** @return array<string, mixed>|null */
    public function getSupportTicket(int $ticketId): ?array
    {
        $ticket = $this->supportTickets[$ticketId] ?? null;
        if ($ticket === null) {
            return null;
        }

        return [
            ...$ticket,
            'tags' => [],
        ];
    }

    /** @return array<string, mixed>|null */
    public function updateSupportTicketStatus(int $ticketId, string $status): ?array
    {
        if (!isset($this->supportTickets[$ticketId])) {
            return null;
        }

        $this->supportTickets[$ticketId]['status'] = $status;

        return $this->supportTickets[$ticketId];
    }
}
