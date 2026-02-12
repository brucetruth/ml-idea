<?php

declare(strict_types=1);

namespace ML\IDEA\Data;

use ML\IDEA\Exceptions\InvalidArgumentException;

final class TimeSeriesSplit
{
    /**
     * @return array<int, array{train: array<int, int>, test: array<int, int>}>
     */
    public static function split(int $nSamples, int $nSplits = 5, ?int $testSize = null): array
    {
        if ($nSamples <= 2) {
            throw new InvalidArgumentException('nSamples must be greater than 2.');
        }

        if ($nSplits <= 1) {
            throw new InvalidArgumentException('nSplits must be greater than 1.');
        }

        $testSize = $testSize ?? max(1, intdiv($nSamples, $nSplits + 1));
        if ($testSize <= 0) {
            throw new InvalidArgumentException('testSize must be positive.');
        }

        $splits = [];
        for ($i = 0; $i < $nSplits; $i++) {
            $trainEnd = ($i + 1) * $testSize;
            $testStart = $trainEnd;
            $testEnd = $testStart + $testSize;

            if ($testEnd > $nSamples) {
                break;
            }

            $train = range(0, $trainEnd - 1);
            $test = range($testStart, $testEnd - 1);
            $splits[] = ['train' => $train, 'test' => $test];
        }

        if ($splits === []) {
            throw new InvalidArgumentException('Unable to create any split with provided arguments.');
        }

        return $splits;
    }
}
