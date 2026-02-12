<?php

declare(strict_types=1);

namespace ML\IDEA\Contracts;

interface ClassifierInterface extends EstimatorInterface
{
    /**
     * @param array<int, array<int, float|int>> $samples
     * @param array<int, int|float|string|bool> $labels
     */
    public function train(array $samples, array $labels): void;

    /**
     * @param array<int, array<int, float|int>> $samples
     * @param array<int, int|float|string|bool> $targets
     */
    public function fit(array $samples, array $targets): void;

    /**
     * @param array<int, float|int> $sample
     */
    public function predict(array $sample): int|float|string|bool;

    /**
     * @param array<int, array<int, float|int>> $samples
     * @return array<int, int|float|string|bool>
     */
    public function predictBatch(array $samples): array;
}
