<?php

declare(strict_types=1);

namespace ML\IDEA\Data;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Support\Assert;

final class TrainTestSplit
{
    /**
     * @param array<int, array<int, float|int>> $samples
     * @param array<int, int|float|string|bool> $labels
     * @return array{
     *     xTrain: array<int, array<int, float|int>>,
     *     xTest: array<int, array<int, float|int>>,
     *     yTrain: array<int, int|float|string|bool>,
     *     yTest: array<int, int|float|string|bool>
     * }
     */
    public static function split(array $samples, array $labels, float $testSize = 0.2, ?int $seed = 42): array
    {
        Assert::numericMatrix($samples);
        Assert::matchingSampleLabelCount($samples, $labels);

        if ($testSize <= 0.0 || $testSize >= 1.0) {
            throw new InvalidArgumentException('testSize must be between 0 and 1 (exclusive).');
        }

        $indices = array_keys($samples);
        if ($seed !== null) {
            mt_srand($seed);
        }
        shuffle($indices);

        $total = count($indices);
        $testCount = max(1, (int) floor($total * $testSize));
        $trainCount = $total - $testCount;

        if ($trainCount <= 0) {
            throw new InvalidArgumentException('Train set is empty. Reduce testSize or increase dataset size.');
        }

        $trainIndices = array_slice($indices, 0, $trainCount);
        $testIndices = array_slice($indices, $trainCount);

        $xTrain = [];
        $yTrain = [];
        foreach ($trainIndices as $index) {
            $xTrain[] = $samples[$index];
            $yTrain[] = $labels[$index];
        }

        $xTest = [];
        $yTest = [];
        foreach ($testIndices as $index) {
            $xTest[] = $samples[$index];
            $yTest[] = $labels[$index];
        }

        return [
            'xTrain' => $xTrain,
            'xTest' => $xTest,
            'yTrain' => $yTrain,
            'yTest' => $yTest,
        ];
    }
}
