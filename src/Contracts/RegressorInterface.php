<?php

declare(strict_types=1);

namespace ML\IDEA\Contracts;

interface RegressorInterface extends EstimatorInterface
{
    /**
     * @param array<int, array<int, float|int>> $samples
     * @param array<int, float|int> $targets
     */
    public function train(array $samples, array $targets): void;

    /**
     * @param array<int, array<int, float|int>> $samples
     * @param array<int, int|float|string|bool> $targets
     */
    public function fit(array $samples, array $targets): void;

    /**
     * @param array<int, float|int> $sample
     */
    public function predict(array $sample): float;

    /**
     * @param array<int, array<int, float|int>> $samples
     * @return array<int, float>
     */
    public function predictBatch(array $samples): array;
}
