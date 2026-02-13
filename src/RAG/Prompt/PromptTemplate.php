<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Prompt;

final class PromptTemplate
{
    /**
     * @param array<int, array{id: string, text: string, metadata: array<string, mixed>, score: float}> $contexts
     */
    public static function retrievalQa(string $question, array $contexts): string
    {
        $contextText = [];
        foreach ($contexts as $i => $ctx) {
            $contextText[] = sprintf('[%d] %s', $i + 1, $ctx['text']);
        }

        return "You are a helpful assistant. Use only the provided context.\n\n"
            . "Context:\n" . implode("\n\n", $contextText) . "\n\n"
            . "Question: {$question}\n"
            . "Answer:";
    }
}
