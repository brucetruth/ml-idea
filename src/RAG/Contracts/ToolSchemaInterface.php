<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Contracts;

interface ToolSchemaInterface
{
    /**
     * @return array<string, mixed>
     */
    public function inputSchema(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function examples(): array;

    public function riskLevel(): string;
}

