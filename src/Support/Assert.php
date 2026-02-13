<?php

declare(strict_types=1);

namespace ML\IDEA\Support;

use ML\IDEA\Exceptions\InvalidArgumentException;

final class Assert
{
    public static function positiveInt(int $value, string $name): void
    {
        if ($value <= 0) {
            throw new InvalidArgumentException(sprintf('%s must be a positive integer.', $name));
        }
    }

    /**
     * @param array<int, array<int, float|int>> $samples
     */
    public static function nonEmptySamples(array $samples): void
    {
        if ($samples === []) {
            throw new InvalidArgumentException('Samples cannot be empty.');
        }
    }

    /**
     * @param array<int, array<int, float|int>> $samples
     */
    public static function numericMatrix(array $samples): void
    {
        self::nonEmptySamples($samples);

        $dimension = null;
        foreach ($samples as $rowIndex => $sample) {
            if ($sample === []) {
                throw new InvalidArgumentException(sprintf('Sample at index %d must be a non-empty numeric vector.', $rowIndex));
            }

            if ($dimension === null) {
                $dimension = count($sample);
            }

            if (count($sample) !== $dimension) {
                throw new InvalidArgumentException('All samples must have the same feature dimension.');
            }

            self::numericVector($sample);
        }
    }

    /**
     * @param array<int, mixed> $vector
     */
    public static function numericVector(array $vector): void
    {
        if ($vector === []) {
            throw new InvalidArgumentException('Vector cannot be empty.');
        }

        foreach ($vector as $value) {
            if (!is_int($value) && !is_float($value)) {
                throw new InvalidArgumentException('Vector must contain only numeric values.');
            }
        }
    }

    /**
     * @param array<int, array<int, float|int>> $samples
     * @param array<int, int|float|string|bool> $labels
     */
    public static function matchingSampleLabelCount(array $samples, array $labels): void
    {
        if (count($samples) !== count($labels)) {
            throw new InvalidArgumentException('Samples and labels must have the same number of rows.');
        }

        if ($labels === []) {
            throw new InvalidArgumentException('Labels cannot be empty.');
        }
    }

    /**
     * @param array<int, mixed> $sample
     */
    public static function sampleMatchesDimension(array $sample, int $dimension): void
    {
        self::numericVector($sample);

        if (count($sample) !== $dimension) {
            throw new InvalidArgumentException(sprintf('Sample must have %d features.', $dimension));
        }
    }
}
