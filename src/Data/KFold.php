<?php

declare(strict_types=1);

namespace ML\IDEA\Data;

use ML\IDEA\Exceptions\InvalidArgumentException;

final class KFold
{
    /**
     * @return array<int, array{train: array<int, int>, test: array<int, int>}>
     */
    public static function split(int $nSamples, int $nSplits = 5, bool $shuffle = true, ?int $seed = 42): array
    {
        if ($nSamples <= 1) {
            throw new InvalidArgumentException('nSamples must be greater than 1.');
        }

        if ($nSplits <= 1 || $nSplits > $nSamples) {
            throw new InvalidArgumentException('nSplits must be > 1 and <= nSamples.');
        }

        $indices = range(0, $nSamples - 1);
        if ($shuffle) {
            if ($seed !== null) {
                mt_srand($seed);
            }
            shuffle($indices);
        }

        $baseFoldSize = intdiv($nSamples, $nSplits);
        $remainder = $nSamples % $nSplits;

        $folds = [];
        $start = 0;
        for ($fold = 0; $fold < $nSplits; $fold++) {
            $size = $baseFoldSize + ($fold < $remainder ? 1 : 0);
            $test = array_slice($indices, $start, $size);
            $train = array_values(array_diff($indices, $test));
            $folds[] = ['train' => $train, 'test' => $test];
            $start += $size;
        }

        return $folds;
    }
}
