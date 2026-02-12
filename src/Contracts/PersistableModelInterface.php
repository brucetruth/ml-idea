<?php

declare(strict_types=1);

namespace ML\IDEA\Contracts;

interface PersistableModelInterface
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): static;
}
