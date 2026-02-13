<?php

declare(strict_types=1);

namespace ML\IDEA\RAG;

final readonly class Chunk
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $id,
        public string $text,
        public array $metadata = [],
    ) {
    }
}
