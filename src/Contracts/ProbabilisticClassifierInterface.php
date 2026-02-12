<?php

declare(strict_types=1);

namespace ML\IDEA\Contracts;

interface ProbabilisticClassifierInterface extends ClassifierInterface
{
    /**
     * @param array<int, float|int> $sample
     * @return array<int|float|string|bool, float>
     */
    public function predictProba(array $sample): array;

    /**
     * @param array<int, array<int, float|int>> $samples
     * @return array<int, array<int|float|string|bool, float>>
     */
    public function predictProbaBatch(array $samples): array;
}
