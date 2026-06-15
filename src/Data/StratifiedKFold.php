<?php

declare(strict_types=1);

namespace ML\IDEA\Data;

use ML\IDEA\Exceptions\InvalidArgumentException;

final class StratifiedKFold
{
    /**
     * @param array<int, int|float|string|bool> $labels
     * @return array<int, array{train: array<int, int>, test: array<int, int>}>
     */
    public static function split(array $labels, int $nSplits = 5, bool $shuffle = true, ?int $seed = 42): array
    {
        if ($labels === []) {
            throw new InvalidArgumentException('labels cannot be empty.');
        }

        $nSamples = count($labels);
        if ($nSplits <= 1 || $nSplits > $nSamples) {
            throw new InvalidArgumentException('nSplits must be > 1 and <= number of samples.');
        }

        $groups = [];
        foreach ($labels as $i => $label) {
            $key = get_debug_type($label) . ':' . json_encode($label, JSON_THROW_ON_ERROR);
            $groups[$key][] = $i;
        }

        $minClassCount = min(array_map('count', $groups));
        if ($nSplits > $minClassCount) {
            throw new InvalidArgumentException(
                sprintf('nSplits=%d cannot be greater than the number of members in each class (%d).', $nSplits, $minClassCount),
            );
        }

        if ($shuffle && $seed !== null) {
            mt_srand($seed);
        }

        /** @var array<int, int> $testFoldByIndex */
        $testFoldByIndex = array_fill(0, $nSamples, 0);

        foreach ($groups as $indices) {
            if ($shuffle) {
                shuffle($indices);
            }

            $classCount = count($indices);
            $baseFoldSize = intdiv($classCount, $nSplits);
            $remainder = $classCount % $nSplits;
            $offset = 0;

            for ($fold = 0; $fold < $nSplits; $fold++) {
                $foldSize = $baseFoldSize + ($fold < $remainder ? 1 : 0);
                for ($j = 0; $j < $foldSize; $j++) {
                    $testFoldByIndex[$indices[$offset + $j]] = $fold;
                }
                $offset += $foldSize;
            }
        }

        $folds = array_fill(0, $nSplits, ['train' => [], 'test' => []]);
        for ($i = 0; $i < $nSamples; $i++) {
            $folds[$testFoldByIndex[$i]]['test'][] = $i;
        }

        $all = range(0, $nSamples - 1);
        foreach ($folds as $i => $fold) {
            sort($folds[$i]['test']);
            $folds[$i]['train'] = array_values(array_diff($all, $folds[$i]['test']));
        }

        return $folds;
    }

    /**
     * @param array<int, int|float|string|bool> $labels
     */
    public static function maxSplits(array $labels): int
    {
        if ($labels === []) {
            return 0;
        }

        $counts = [];
        foreach ($labels as $label) {
            $key = get_debug_type($label) . ':' . json_encode($label, JSON_THROW_ON_ERROR);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return min($counts);
    }
}
