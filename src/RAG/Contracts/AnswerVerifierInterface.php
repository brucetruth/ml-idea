<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Contracts;

interface AnswerVerifierInterface
{
    /**
     * @param array<int, array{id: string, text: string, metadata: array<string, mixed>, score: float}> $contexts
     * @return array{is_valid: bool, issues: array<int, string>}
     */
    public function verify(string $question, string $answer, array $contexts): array;
}
