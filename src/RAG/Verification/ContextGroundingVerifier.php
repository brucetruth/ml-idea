<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Verification;

use ML\IDEA\RAG\Contracts\AnswerVerifierInterface;

final class ContextGroundingVerifier implements AnswerVerifierInterface
{
    public function verify(string $question, string $answer, array $contexts): array
    {
        $issues = [];

        if (trim($answer) === '') {
            $issues[] = 'Empty answer returned.';
        }

        if ($contexts === []) {
            $issues[] = 'No retrieved contexts.';
        }

        $hasNonZeroScore = false;
        foreach ($contexts as $ctx) {
            if ($ctx['score'] > 0.0) {
                $hasNonZeroScore = true;
                break;
            }
        }

        if (!$hasNonZeroScore) {
            $issues[] = 'All retrieved chunk scores are zero.';
        }

        return [
            'is_valid' => $issues === [],
            'issues' => $issues,
        ];
    }
}
