<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

final class AgentPolicy
{
    /**
     * @param array<int, string> $allowedTools
     * @param array<int, string> $blockedTools
     * @param array<int, string> $confirmationRequiredRiskLevels
     * @param null|callable(string, string, array<string, mixed>): bool $confirmationCallback
     */
    public function __construct(
        private readonly array $allowedTools = [],
        private readonly array $blockedTools = [],
        private readonly int $maxToolCalls = 16,
        private readonly int $maxToolOutputBytes = 12000,
        private readonly array $confirmationRequiredRiskLevels = ['high'],
        private readonly mixed $confirmationCallback = null,
        private readonly bool $pauseForApproval = false,
    ) {
    }

    public static function default(): self
    {
        return new self();
    }

    /** @param array<string, mixed> $input */
    public function canUseTool(string $toolName, string $riskLevel, array $input = []): ?string
    {
        if (in_array($toolName, $this->blockedTools, true)) {
            return sprintf('Tool is blocked by policy: %s', $toolName);
        }

        if ($this->allowedTools !== [] && !in_array($toolName, $this->allowedTools, true)) {
            return sprintf('Tool is not in the policy allow-list: %s', $toolName);
        }

        if (in_array($riskLevel, $this->confirmationRequiredRiskLevels, true)) {
            if ($this->isApproved($toolName, $riskLevel, $input)) {
                return null;
            }

            return sprintf('Tool risk level requires external confirmation: %s', $riskLevel);
        }

        return null;
    }

    /** @param array<string, mixed> $input */
    public function shouldPauseForApproval(string $toolName, string $riskLevel, array $input = []): bool
    {
        if (!$this->pauseForApproval) {
            return false;
        }

        if (!in_array($riskLevel, $this->confirmationRequiredRiskLevels, true)) {
            return false;
        }

        return !$this->isApproved($toolName, $riskLevel, $input);
    }

    public function pauseForApprovalEnabled(): bool
    {
        return $this->pauseForApproval;
    }

    /** @param array<string, mixed> $input */
    public function approvalToken(string $toolName, array $input): string
    {
        return substr(hash('sha256', $toolName . json_encode($input, JSON_THROW_ON_ERROR)), 0, 16);
    }

    /** @param array<string, mixed> $input */
    private function isApproved(string $toolName, string $riskLevel, array $input): bool
    {
        return is_callable($this->confirmationCallback)
            && (bool) call_user_func($this->confirmationCallback, $toolName, $riskLevel, $input);
    }

    public function maxToolCalls(): int
    {
        return $this->maxToolCalls;
    }

    public function maxToolOutputBytes(): int
    {
        return $this->maxToolOutputBytes;
    }
}
