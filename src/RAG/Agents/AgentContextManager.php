<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

final class AgentContextManager
{
    public function __construct(
        private readonly int $maxRoutingMessages = 24,
        private readonly int $maxToolMessageChars = 4000,
        private readonly int $preserveInitialMessages = 2,
    ) {
        if ($this->maxRoutingMessages < 2) {
            throw new \InvalidArgumentException('maxRoutingMessages must be at least 2.');
        }
        if ($this->preserveInitialMessages < 1) {
            throw new \InvalidArgumentException('preserveInitialMessages must be at least 1.');
        }
    }

    public static function default(): self
    {
        return new self();
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, array<string, mixed>>
     */
    public function prepareForRouting(array $messages): array
    {
        return $this->windowMessages($this->compressToolMessages($messages));
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, array<string, mixed>>
     */
    private function compressToolMessages(array $messages): array
    {
        $out = [];
        foreach ($messages as $message) {
            $role = isset($message['role']) ? (string) $message['role'] : '';
            $content = isset($message['content']) ? (string) $message['content'] : '';
            if ($this->shouldCompressMessage($role, $content) && strlen($content) > $this->maxToolMessageChars) {
                $message['content'] = substr($content, 0, $this->maxToolMessageChars) . '...[context_truncated]';
            }
            $out[] = $message;
        }

        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, array<string, mixed>>
     */
    private function windowMessages(array $messages): array
    {
        if (count($messages) <= $this->maxRoutingMessages) {
            return $messages;
        }

        $headCount = min($this->preserveInitialMessages, count($messages));
        $head = array_slice($messages, 0, $headCount);
        $tailCount = max(0, $this->maxRoutingMessages - $headCount - 1);
        $tail = $tailCount > 0 ? array_slice($messages, -$tailCount) : [];
        $omitted = count($messages) - count($head) - count($tail);

        $window = $head;
        if ($omitted > 0) {
            $window[] = [
                'role' => 'system',
                'content' => sprintf('[%d earlier messages omitted from routing context]', $omitted),
            ];
        }

        return array_merge($window, $tail);
    }

    private function shouldCompressMessage(string $role, string $content): bool
    {
        if ($role === 'tool') {
            return true;
        }

        if (str_starts_with($content, 'TOOL_CALL ') || str_starts_with($content, 'PLAN ') || str_starts_with($content, 'REFLECT ')) {
            return true;
        }

        return str_starts_with(trim($content), '{') && str_contains($content, '"ok"');
    }
}
