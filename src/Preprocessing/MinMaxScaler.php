<?php

declare(strict_types=1);

namespace ML\IDEA\Preprocessing;

use ML\IDEA\Exceptions\ModelNotTrainedException;
use ML\IDEA\Support\Assert;

final class MinMaxScaler extends AbstractTransformer
{
    /** @var array<int, float> */
    private array $mins = [];

    /** @var array<int, float> */
    private array $maxs = [];

    private int $featureCount = 0;
    private bool $fitted = false;

    public function fit(array $samples): void
    {
        Assert::numericMatrix($samples);

        $this->featureCount = count($samples[0]);
        $this->mins = array_fill(0, $this->featureCount, INF);
        $this->maxs = array_fill(0, $this->featureCount, -INF);

        foreach ($samples as $sample) {
            foreach ($sample as $j => $value) {
                $x = (float) $value;
                if ($x < $this->mins[$j]) {
                    $this->mins[$j] = $x;
                }
                if ($x > $this->maxs[$j]) {
                    $this->maxs[$j] = $x;
                }
            }
        }

        $this->fitted = true;
    }

    public function transform(array $samples): array
    {
        if (!$this->fitted) {
            throw new ModelNotTrainedException('MinMaxScaler must be fitted before transform.');
        }

        Assert::numericMatrix($samples);

        $transformed = [];
        foreach ($samples as $sample) {
            Assert::sampleMatchesDimension($sample, $this->featureCount);
            $row = [];
            foreach ($sample as $j => $value) {
                $range = $this->maxs[$j] - $this->mins[$j];
                if ($range == 0.0) {
                    $row[] = 0.0;
                    continue;
                }
                $row[] = ((float) $value - $this->mins[$j]) / $range;
            }
            $transformed[] = $row;
        }

        return $transformed;
    }
}
