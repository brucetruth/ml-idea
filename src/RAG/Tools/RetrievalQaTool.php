<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Tools;

use ML\IDEA\RAG\Chains\RetrievalQAChain;
use ML\IDEA\RAG\Contracts\ToolInterface;
use ML\IDEA\RAG\Contracts\ToolSchemaInterface;

final class RetrievalQaTool implements ToolInterface, ToolSchemaInterface
{
    public function __construct(private readonly RetrievalQAChain $chain)
    {
    }

    public function name(): string
    {
        return 'rag_qa';
    }

    public function description(): string
    {
        return 'Answer questions using retrieval-augmented generation.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['question'],
            'properties' => [
                'question' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 4000],
                'k' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
            ],
        ];
    }

    public function examples(): array
    {
        return [
            ['question' => 'How can I persist models?', 'k' => 3],
        ];
    }

    public function riskLevel(): string
    {
        return 'low';
    }

    public function invoke(array $input): string
    {
        $question = isset($input['question']) ? (string) $input['question'] : '';
        $k = isset($input['k']) ? (int) $input['k'] : 3;
        if ($question === '') {
            return 'Missing required field: question';
        }

        $result = $this->chain->ask($question, $k);
        return $result['answer'];
    }
}
