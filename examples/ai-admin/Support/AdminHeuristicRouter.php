<?php

declare(strict_types=1);

namespace ML\IDEA\Examples\AiAdmin\Support;

use ML\IDEA\RAG\Contracts\ToolRoutingModelInterface;

/**
 * Deterministic router for offline AI-admin demos.
 * Provider-backed models (OpenAI, Anthropic, etc.) can route to the same tools in production.
 */
final class AdminHeuristicRouter implements ToolRoutingModelInterface
{
    public function respond(array $messages, array $tools): array
    {
        $lastUser = $this->lastUserMessage($messages);
        $lastToolOutput = $this->lastToolOutputAfterLastUser($messages);

        if (str_contains($lastUser, 'new refund request #')) {
            return $this->routeRefundRequestTriage($messages, $lastUser);
        }

        if (str_contains($lastUser, 'support ticket #')) {
            return $this->routeSupportTicketTriage($messages, $lastUser);
        }

        if ($lastToolOutput !== null && $lastToolOutput !== '') {
            return ['type' => 'final', 'content' => $this->summarizeToolOutput($lastToolOutput)];
        }

        if (str_contains($lastUser, 'status') && preg_match('/user\s*#?(\d+)/', $lastUser, $matches) === 1) {
            return [
                'type' => 'tool_call',
                'tool' => 'get_user',
                'input' => ['user_id' => (int) $matches[1]],
            ];
        }

        if (str_contains($lastUser, 'refund') && preg_match('/order\s*#?(\d+)/', $lastUser, $matches) === 1) {
            return [
                'type' => 'tool_call',
                'tool' => 'refund_order',
                'input' => ['order_id' => (int) $matches[1], 'reason' => 'admin requested refund'],
            ];
        }

        if (str_contains($lastUser, 'ban') && preg_match('/user\s*#?(\d+)/', $lastUser, $matches) === 1) {
            return [
                'type' => 'tool_call',
                'tool' => 'ban_user',
                'input' => ['user_id' => (int) $matches[1], 'reason' => 'policy violation'],
            ];
        }

        if (str_contains($lastUser, 'role') && preg_match('/user\s*#?(\d+).*(admin|editor|customer)/', $lastUser, $matches) === 1) {
            return [
                'type' => 'tool_call',
                'tool' => 'update_user_role',
                'input' => ['user_id' => (int) $matches[1], 'role' => $matches[2]],
            ];
        }

        if (str_contains($lastUser, 'order')) {
            $userId = preg_match('/user\s*#?(\d+)/', $lastUser, $matches) === 1 ? (int) $matches[1] : null;

            return [
                'type' => 'tool_call',
                'tool' => 'list_orders',
                'input' => array_filter(['user_id' => $userId]),
            ];
        }

        if (preg_match('/user\s*#?(\d+)/', $lastUser, $matches) === 1) {
            return [
                'type' => 'tool_call',
                'tool' => 'get_user',
                'input' => ['user_id' => (int) $matches[1]],
            ];
        }

        if (str_contains($lastUser, 'list') && str_contains($lastUser, 'user')) {
            return ['type' => 'tool_call', 'tool' => 'list_users', 'input' => []];
        }

        return [
            'type' => 'final',
            'content' => 'I can list users, inspect user #id, update roles, ban users, list orders, refund orders, add notes, tag orders, and update support tickets.',
        ];
    }

    /**
     * Multi-step autonomous support triage — low/medium tools only, no human approval.
     *
     * @param array<int, array<string, mixed>> $messages
     * @return array<string, mixed>
     */
    private function routeSupportTicketTriage(array $messages, string $lastUser): array
    {
        if (!preg_match('/support ticket #(\d+)/', $lastUser, $ticketMatch)) {
            return ['type' => 'final', 'content' => 'Invalid support ticket payload.'];
        }

        $ticketId = (int) $ticketMatch[1];
        $userId = preg_match('/user\s*#?(\d+)/', $lastUser, $userMatch) === 1 ? (int) $userMatch[1] : 2;
        $orderId = preg_match('/order\s*#?(\d+)/', $lastUser, $orderMatch) === 1 ? (int) $orderMatch[1] : 101;

        $sawUser = false;
        $sawOrders = false;
        $sawNote = false;
        $sawTag = false;
        $sawTicketUpdate = false;

        foreach ($this->allToolOutputsAfterLastUser($messages) as $output) {
            $decoded = json_decode($output, true);
            if (!is_array($decoded)) {
                continue;
            }
            if (isset($decoded['output']) && is_string($decoded['output'])) {
                $decoded = json_decode($decoded['output'], true);
            }
            if (!is_array($decoded)) {
                continue;
            }
            if (isset($decoded['user']) && is_array($decoded['user'])) {
                $sawUser = true;
            }
            if (isset($decoded['orders']) && is_array($decoded['orders'])) {
                $sawOrders = true;
            }
            if (isset($decoded['notes']) && is_array($decoded['notes'])) {
                $sawNote = true;
            }
            if (isset($decoded['tags']) && is_array($decoded['tags'])) {
                $sawTag = true;
            }
            if (isset($decoded['ticket']) && is_array($decoded['ticket'])) {
                $sawTicketUpdate = true;
            }
        }

        if (!$sawUser) {
            return ['type' => 'tool_call', 'tool' => 'get_user', 'input' => ['user_id' => $userId]];
        }

        if (!$sawOrders) {
            return ['type' => 'tool_call', 'tool' => 'list_orders', 'input' => ['user_id' => $userId]];
        }

        if (!$sawNote) {
            return [
                'type' => 'tool_call',
                'tool' => 'add_user_note',
                'input' => ['user_id' => $userId, 'note' => 'Billing review opened for ticket #' . $ticketId . '.'],
            ];
        }

        if (!$sawTag) {
            return [
                'type' => 'tool_call',
                'tool' => 'tag_order',
                'input' => ['order_id' => $orderId, 'tag' => 'billing-review'],
            ];
        }

        if (!$sawTicketUpdate) {
            return [
                'type' => 'tool_call',
                'tool' => 'update_support_ticket_status',
                'input' => ['ticket_id' => $ticketId, 'status' => 'pending'],
            ];
        }

        return [
            'type' => 'final',
            'content' => sprintf(
                'Ticket #%d triaged: user #%d reviewed, order #%d tagged billing-review, ticket set to pending. No human approval required.',
                $ticketId,
                $userId,
                $orderId,
            ),
        ];
    }

    /** @param array<int, array<string, mixed>> $messages */
    private function lastUserMessage(array $messages): string
    {
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (($messages[$i]['role'] ?? '') === 'user') {
                return strtolower((string) ($messages[$i]['content'] ?? ''));
            }
        }

        return '';
    }

    /** @param array<int, array<string, mixed>> $messages */
    private function lastToolOutputAfterLastUser(array $messages): ?string
    {
        $lastUserIndex = null;
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (($messages[$i]['role'] ?? '') === 'user') {
                $lastUserIndex = $i;
                break;
            }
        }

        if ($lastUserIndex === null) {
            return null;
        }

        for ($i = count($messages) - 1; $i > $lastUserIndex; $i--) {
            if (($messages[$i]['role'] ?? '') === 'tool') {
                $content = $messages[$i]['content'] ?? '';

                return is_string($content) ? $content : '';
            }
        }

        return null;
    }

    private function summarizeToolOutput(string $output): string
    {
        $decoded = json_decode($output, true);
        if (!is_array($decoded)) {
            return 'Admin action completed: ' . $output;
        }

        if (isset($decoded['output']) && is_string($decoded['output'])) {
            return $this->summarizeToolOutput($decoded['output']);
        }

        if (isset($decoded['users']) && is_array($decoded['users'])) {
            return sprintf('Found %d users.', count($decoded['users']));
        }
        if (isset($decoded['orders']) && is_array($decoded['orders'])) {
            return sprintf('Found %d orders.', count($decoded['orders']));
        }
        if (isset($decoded['user']) && is_array($decoded['user'])) {
            $user = $decoded['user'];

            return sprintf('User #%d (%s) is %s with role %s.', $user['id'], $user['name'], $user['status'], $user['role']);
        }
        if (($decoded['ok'] ?? false) === true) {
            return (string) ($decoded['message'] ?? 'Admin action completed successfully.');
        }

        return (string) ($decoded['error'] ?? $output);
    }

    /**
     * Multi-step triage for customer-submitted refund requests (one user message, several tool iterations).
     *
     * @param array<int, array<string, mixed>> $messages
     * @return array<string, mixed>
     */
    private function routeRefundRequestTriage(array $messages, string $lastUser): array
    {
        if (!preg_match('/user_id=(\d+)/', $lastUser, $userMatch) || !preg_match('/order_id=(\d+)/', $lastUser, $orderMatch)) {
            return ['type' => 'final', 'content' => 'Invalid refund request payload; missing user_id or order_id.'];
        }

        $userId = (int) $userMatch[1];
        $orderId = (int) $orderMatch[1];
        $reason = 'customer refund request';

        $sawUser = false;
        $sawOrders = false;
        $targetOrderStatus = null;

        foreach ($this->allToolOutputsAfterLastUser($messages) as $output) {
            $decoded = json_decode($output, true);
            if (!is_array($decoded)) {
                continue;
            }
            if (isset($decoded['output']) && is_string($decoded['output'])) {
                $decoded = json_decode($decoded['output'], true);
            }
            if (!is_array($decoded)) {
                continue;
            }
            if (isset($decoded['user']) && is_array($decoded['user'])) {
                $sawUser = true;
            }
            if (isset($decoded['orders']) && is_array($decoded['orders'])) {
                $sawOrders = true;
                foreach ($decoded['orders'] as $order) {
                    if (is_array($order) && (int) ($order['id'] ?? 0) === $orderId) {
                        $targetOrderStatus = (string) ($order['status'] ?? '');
                    }
                }
            }
            if (($decoded['ok'] ?? false) === true && str_contains((string) ($decoded['message'] ?? ''), 'Refunded')) {
                return ['type' => 'final', 'content' => 'Decision: APPROVED — refund processed for order #' . $orderId . '.'];
            }
        }

        if (!$sawUser) {
            return ['type' => 'tool_call', 'tool' => 'get_user', 'input' => ['user_id' => $userId]];
        }

        if (!$sawOrders) {
            return ['type' => 'tool_call', 'tool' => 'list_orders', 'input' => ['user_id' => $userId]];
        }

        if ($targetOrderStatus === 'shipped') {
            return [
                'type' => 'final',
                'content' => 'Decision: DENY — order #' . $orderId . ' already shipped; not eligible for automatic refund. Escalate to support.',
            ];
        }

        if ($targetOrderStatus === 'refunded') {
            return ['type' => 'final', 'content' => 'Decision: DENY — order #' . $orderId . ' was already refunded.'];
        }

        if ($targetOrderStatus === 'paid') {
            return [
                'type' => 'tool_call',
                'tool' => 'refund_order',
                'input' => ['order_id' => $orderId, 'reason' => $reason],
            ];
        }

        return [
            'type' => 'final',
            'content' => 'Decision: ESCALATE — unable to verify order #' . $orderId . ' automatically.',
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, string>
     */
    private function allToolOutputsAfterLastUser(array $messages): array
    {
        $lastUserIndex = null;
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (($messages[$i]['role'] ?? '') === 'user') {
                $lastUserIndex = $i;
                break;
            }
        }

        if ($lastUserIndex === null) {
            return [];
        }

        $outputs = [];
        for ($i = $lastUserIndex + 1; $i < count($messages); $i++) {
            if (($messages[$i]['role'] ?? '') === 'tool') {
                $content = $messages[$i]['content'] ?? '';
                if (is_string($content) && $content !== '') {
                    $outputs[] = $content;
                }
            }
        }

        return $outputs;
    }
}
