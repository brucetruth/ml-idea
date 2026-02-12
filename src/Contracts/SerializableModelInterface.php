<?php

declare(strict_types=1);

namespace ML\IDEA\Contracts;

interface SerializableModelInterface extends PersistableModelInterface
{
    public function save(string $path): void;

    public static function load(string $path): static;
}
