<?php

declare(strict_types=1);

namespace ML\IDEA\Preprocessing;

use ML\IDEA\Exceptions\ModelNotTrainedException;
use ML\IDEA\Support\Assert;

final class StandardScaler extends AbstractTransformer
{
    /** @var array<int, float> */
    private array $means = [];

    /** @var array<int, float> */
    private array $stdDevs = [];

    private int $featureCount = 0;
    private bool $fitted = false;

    public function fit(array $samples): void
    {
        Assert::numericMatrix($samples);

        $this->featureCount = count($samples[0]);
        $count = count($samples);

        $this->means = array_fill(0, $this->featureCount, 0.0);
        foreach ($samples as $sample) {
            foreach ($sample as $j => $value) {
                $this->means[$j] += (float) $value;
            }
        }
        foreach ($this->means as $j => $sum) {
            $this->means[$j] = $sum / $count;
        }

        $this->stdDevs = array_fill(0, $this->featureCount, 0.0);
        foreach ($samples as $sample) {
            foreach ($sample as $j => $value) {
                $delta = (float) $value - $this->means[$j];
                $this->stdDevs[$j] += $delta * $delta;
            }
        }
        foreach ($this->stdDevs as $j => $sumSquares) {
            $variance = $sumSquares / max(1, $count - 1);
            $std = sqrt($variance);
            $this->stdDevs[$j] = $std > 0.0 ? $std : 1.0;
        }

        $this->fitted = true;
    }

    public function transform(array $samples): array
    {
        if (!$this->fitted) {
            throw new ModelNotTrainedException('StandardScaler must be fitted before transform.');
        }

        Assert::numericMatrix($samples);

        $transformed = [];
        foreach ($samples as $sample) {
            Assert::sampleMatchesDimension($sample, $this->featureCount);
            $row = [];
            foreach ($sample as $j => $value) {
                $row[] = ((float) $value - $this->means[$j]) / $this->stdDevs[$j];
            }
            $transformed[] = $row;
        }

        return $transformed;
    }
}
