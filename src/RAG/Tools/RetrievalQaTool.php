<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Tools;

use ML\IDEA\RAG\Chains\RetrievalQAChain;
use ML\IDEA\RAG\Contracts\ToolInterface;

final class RetrievalQaTool implements ToolInterface
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
