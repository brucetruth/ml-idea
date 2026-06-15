<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Agents;

final class AgentRunLogEntry
{
    /**
     * @param array<int, array<string, mixed>> $toolCalls
     * @param array<int, array<string, mixed>> $decisions
     * @param array<string, mixed> $usage
     * @param array<string, mixed> $budget
     * @param array<string, mixed>|null $telemetry
     * @param array<string, mixed>|null $pendingApproval
     */
    public function __construct(
        public readonly string $id,
        public readonly string $loggedAt,
        public readonly string $agentName,
        public readonly ?string $sessionId,
        public readonly ?string $userMessage,
        public readonly bool $resume,
        public readonly string $answer,
        public readonly string $stopReason,
        public readonly int $iterations,
        public readonly array $toolCalls,
        public readonly array $decisions,
        public readonly array $usage,
        public readonly array $budget,
        public readonly ?array $telemetry,
        public readonly ?array $pendingApproval,
    ) {
    }

    /**
     * @param array<string, mixed> $result
     */
    public static function fromAgentResult(
        array $result,
        string $agentName,
        ?AgentRunLogContext $context = null,
        ?TraceRedactor $redactor = null,
        int $maxUserMessageChars = 4000,
        int $maxAnswerChars = 8000,
        int $maxToolOutputChars = 4000,
    ): self {
        $redactor ??= new TraceRedactor();
        $context ??= new AgentRunLogContext();

        $userMessage = self::truncate((string) ($context->userMessage ?? ''), $maxUserMessageChars);
        $answer = self::truncate((string) ($result['answer'] ?? ''), $maxAnswerChars);

        $toolCalls = [];
        foreach (is_array($result['tool_calls'] ?? null) ? $result['tool_calls'] : [] as $call) {
            if (!is_array($call)) {
                continue;
            }
            $output = (string) ($call['output'] ?? '');
            $toolCalls[] = $redactor->redactArray([
                'name' => (string) ($call['name'] ?? ''),
                'input' => is_array($call['input'] ?? null) ? $call['input'] : [],
                'output' => self::truncate($output, $maxToolOutputChars),
            ]);
        }

        $decisions = [];
        foreach (is_array($result['decisions'] ?? null) ? $result['decisions'] : [] as $decision) {
            if (!is_array($decision)) {
                continue;
            }
            $decisions[] = $redactor->redactArray($decision);
        }

        $pendingApproval = is_array($result['pending_approval'] ?? null)
            ? $redactor->redactArray($result['pending_approval'])
            : null;

        $telemetry = is_array($result['telemetry'] ?? null) ? $result['telemetry'] : null;

        return new self(
            id: bin2hex(random_bytes(16)),
            loggedAt: gmdate('c'),
            agentName: $agentName,
            sessionId: $context->sessionId,
            userMessage: $userMessage !== '' ? $userMessage : null,
            resume: $context->resume,
            answer: $answer,
            stopReason: (string) ($result['stop_reason'] ?? ''),
            iterations: (int) ($result['iterations'] ?? 0),
            toolCalls: $toolCalls,
            decisions: $decisions,
            usage: is_array($result['usage'] ?? null) ? $result['usage'] : [],
            budget: is_array($result['budget'] ?? null) ? $result['budget'] : [],
            telemetry: $telemetry,
            pendingApproval: $pendingApproval,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'logged_at' => $this->loggedAt,
            'agent_name' => $this->agentName,
            'session_id' => $this->sessionId,
            'user_message' => $this->userMessage,
            'resume' => $this->resume,
            'answer' => $this->answer,
            'stop_reason' => $this->stopReason,
            'iterations' => $this->iterations,
            'tool_calls' => $this->toolCalls,
            'decisions' => $this->decisions,
            'usage' => $this->usage,
            'budget' => $this->budget,
            'telemetry' => $this->telemetry,
            'pending_approval' => $this->pendingApproval,
        ];
    }

    private static function truncate(string $value, int $maxChars): string
    {
        if ($maxChars <= 0 || strlen($value) <= $maxChars) {
            return $value;
        }

        return substr($value, 0, $maxChars - 3) . '...';
    }
}
