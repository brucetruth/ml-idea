<?php

declare(strict_types=1);

namespace ML\IDEA\Contracts;

interface EstimatorInterface extends ParametricInterface, RandomStateAwareInterface
{
    /**
     * @param array<int, array<int, float|int>> $samples
     * @param array<int, int|float|string|bool> $targets
     */
    public function fit(array $samples, array $targets): void;

    /**
     * @param array<int, float|int> $sample
     */
    public function predict(array $sample): mixed;

    /**
     * @param array<int, array<int, float|int>> $samples
     * @return array<int, mixed>
     */
    public function predictBatch(array $samples): array;
}
