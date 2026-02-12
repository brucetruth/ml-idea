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

        if ($shuffle && $seed !== null) {
            mt_srand($seed);
        }

        $folds = array_fill(0, $nSplits, ['train' => [], 'test' => []]);
        foreach ($groups as $indices) {
            if ($shuffle) {
                shuffle($indices);
            }

            foreach ($indices as $offset => $index) {
                $foldIndex = $offset % $nSplits;
                $folds[$foldIndex]['test'][] = $index;
            }
        }

        $all = range(0, $nSamples - 1);
        foreach ($folds as $i => $fold) {
            sort($folds[$i]['test']);
            $folds[$i]['train'] = array_values(array_diff($all, $folds[$i]['test']));
        }

        return $folds;
    }
}
