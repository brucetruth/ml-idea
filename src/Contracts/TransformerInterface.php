<?php

declare(strict_types=1);

namespace ML\IDEA\Contracts;

interface TransformerInterface extends ParametricInterface, RandomStateAwareInterface
{
    /**
     * @param array<int, array<int, float|int>> $samples
     */
    public function fit(array $samples): void;

    /**
     * @param array<int, array<int, float|int>> $samples
     * @return array<int, array<int, float>>
     */
    public function transform(array $samples): array;

    /**
     * @param array<int, array<int, float|int>> $samples
     * @return array<int, array<int, float>>
     */
    public function fitTransform(array $samples): array;
}
