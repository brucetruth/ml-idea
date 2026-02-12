<?php

declare(strict_types=1);

namespace ML\IDEA\Contracts;

interface ParametricInterface
{
    /** @return array<string, mixed> */
    public function getParams(): array;

    /**
     * @param array<string, mixed> $params
     */
    public function setParams(array $params): static;

    /**
     * @param array<string, mixed> $params
     */
    public function cloneWithParams(array $params): static;
}
