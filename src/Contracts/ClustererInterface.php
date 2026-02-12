<?php

declare(strict_types=1);

namespace ML\IDEA\Contracts;

interface ClustererInterface
{
    /**
     * @param array<int, array<int, float|int>> $samples
     */
    public function fit(array $samples): void;

    /**
     * @param array<int, float|int> $sample
     */
    public function predict(array $sample): int;

    /**
     * @param array<int, array<int, float|int>> $samples
     * @return array<int, int>
     */
    public function predictBatch(array $samples): array;
}
