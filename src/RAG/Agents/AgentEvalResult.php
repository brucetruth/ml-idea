<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

final class AgentEvalResult
{
    /**
     * @param array<string, mixed> $expect
     * @param array<string, mixed> $actual
     */
    public function __construct(
        public readonly string $name,
        public readonly bool $passed,
        public readonly array $expect,
        public readonly array $actual,
        public readonly string $message = '',
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'passed' => $this->passed,
            'expect' => $this->expect,
            'actual' => $this->actual,
            'message' => $this->message,
        ];
    }
}
