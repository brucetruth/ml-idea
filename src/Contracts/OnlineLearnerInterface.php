<?php

declare(strict_types=1);

namespace ML\IDEA\Contracts;

interface OnlineLearnerInterface
{
    /**
     * @param array<int, array<int, float|int>> $samples
     * @param array<int, int|float|string|bool> $targets
     */
    public function partialFit(array $samples, array $targets): void;
}
