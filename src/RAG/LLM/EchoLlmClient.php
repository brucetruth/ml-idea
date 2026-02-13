<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\LLM;

use ML\IDEA\RAG\Contracts\LlmClientInterface;
use ML\IDEA\RAG\Contracts\StreamingLlmClientInterface;

final class EchoLlmClient implements LlmClientInterface, StreamingLlmClientInterface
{
    public function generate(string $prompt, array $options = []): string
    {
        $prefix = isset($options['prefix']) && is_string($options['prefix'])
            ? $options['prefix']
            : 'ECHO';

        return $prefix . ': ' . substr($prompt, 0, 240);
    }

    public function streamGenerate(string $prompt, array $options = []): iterable
    {
        $text = $this->generate($prompt, $options);
        $chunkSize = 40;

        $offset = 0;
        $length = strlen($text);
        while ($offset < $length) {
            yield substr($text, $offset, $chunkSize);
            $offset += $chunkSize;
        }
    }
}
