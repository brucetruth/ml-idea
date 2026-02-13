<?php

declare(strict_types=1);

namespace ML\IDEA\Decomposition;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Exceptions\ModelNotTrainedException;
use ML\IDEA\Support\Assert;

final class PCA
{
    /** @var array<int, float> */
    private array $means = [];

    /** @var array<int, array<int, float>> */
    private array $components = [];

    private int $featureCount = 0;
    private bool $fitted = false;

    public function __construct(private readonly int $nComponents = 2, private readonly int $powerIterations = 50)
    {
        if ($this->nComponents <= 0) {
            throw new InvalidArgumentException('nComponents must be positive.');
        }

        if ($this->powerIterations <= 0) {
            throw new InvalidArgumentException('powerIterations must be positive.');
        }
    }

    /**
     * @param array<int, array<int, float|int>> $samples
     */
    public function fit(array $samples): void
    {
        Assert::numericMatrix($samples);

        $nSamples = count($samples);
        $this->featureCount = count($samples[0]);
        if ($this->nComponents > $this->featureCount) {
            throw new InvalidArgumentException('nComponents cannot exceed feature count.');
        }

        $this->means = array_fill(0, $this->featureCount, 0.0);
        foreach ($samples as $sample) {
            foreach ($sample as $j => $value) {
                $this->means[$j] += (float) $value;
            }
        }
        foreach ($this->means as $j => $sum) {
            $this->means[$j] = $sum / $nSamples;
        }

        $centered = [];
        foreach ($samples as $sample) {
            $row = [];
            foreach ($sample as $j => $value) {
                $row[] = (float) $value - $this->means[$j];
            }
            $centered[] = $row;
        }

        $cov = array_fill(0, $this->featureCount, array_fill(0, $this->featureCount, 0.0));
        foreach ($centered as $row) {
            for ($i = 0; $i < $this->featureCount; $i++) {
                for ($j = 0; $j < $this->featureCount; $j++) {
                    $cov[$i][$j] += $row[$i] * $row[$j];
                }
            }
        }
        $denominator = max(1, $nSamples - 1);
        for ($i = 0; $i < $this->featureCount; $i++) {
            for ($j = 0; $j < $this->featureCount; $j++) {
                $cov[$i][$j] /= $denominator;
            }
        }

        $components = [];
        for ($k = 0; $k < $this->nComponents; $k++) {
            $vector = array_fill(0, $this->featureCount, 1.0 / max(1, $this->featureCount));

            for ($iter = 0; $iter < $this->powerIterations; $iter++) {
                $vector = self::matrixVectorMultiply($cov, $vector);

                foreach ($components as $existing) {
                    $projection = self::dot($vector, $existing);
                    for ($i = 0; $i < $this->featureCount; $i++) {
                        $vector[$i] -= $projection * $existing[$i];
                    }
                }

                $norm = self::norm($vector);
                if ($norm <= 1.0e-12) {
                    break;
                }
                for ($i = 0; $i < $this->featureCount; $i++) {
                    $vector[$i] /= $norm;
                }
            }

            $components[] = $vector;
        }

        $this->components = $components;
        $this->fitted = true;
    }

    /**
     * @param array<int, array<int, float|int>> $samples
     * @return array<int, array<int, float>>
     */
    public function transform(array $samples): array
    {
        if (!$this->fitted) {
            throw new ModelNotTrainedException('PCA must be fitted before transform.');
        }

        Assert::numericMatrix($samples);

        $result = [];
        foreach ($samples as $sample) {
            Assert::sampleMatchesDimension($sample, $this->featureCount);

            $centered = [];
            foreach ($sample as $j => $value) {
                $centered[] = (float) $value - $this->means[$j];
            }

            $projected = [];
            foreach ($this->components as $component) {
                $projected[] = self::dot($centered, $component);
            }

            $result[] = $projected;
        }

        return $result;
    }

    /**
     * @param array<int, array<int, float|int>> $samples
     * @return array<int, array<int, float>>
     */
    public function fitTransform(array $samples): array
    {
        $this->fit($samples);
        return $this->transform($samples);
    }

    /** @param array<int, array<int, float>> $matrix @param array<int, float> $vector @return array<int, float> */
    private static function matrixVectorMultiply(array $matrix, array $vector): array
    {
        $result = [];
        foreach ($matrix as $row) {
            $sum = 0.0;
            foreach ($row as $j => $value) {
                $sum += $value * $vector[$j];
            }
            $result[] = $sum;
        }

        return $result;
    }

    /** @param array<int, float> $a @param array<int, float> $b */
    private static function dot(array $a, array $b): float
    {
        $sum = 0.0;
        foreach ($a as $i => $value) {
            $sum += $value * $b[$i];
        }

        return $sum;
    }

    /** @param array<int, float> $vector */
    private static function norm(array $vector): float
    {
        $sum = 0.0;
        foreach ($vector as $value) {
            $sum += $value * $value;
        }

        return sqrt($sum);
    }
}
