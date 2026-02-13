<?php

declare(strict_types=1);

namespace ML\IDEA\Preprocessing;

use ML\IDEA\Exceptions\ModelNotTrainedException;
use ML\IDEA\Support\Assert;

final class PolynomialFeatures extends AbstractTransformer
{
    private int $featureCount = 0;
    private bool $fitted = false;

    public function __construct(private readonly int $degree = 2)
    {
    }

    public function fit(array $samples): void
    {
        Assert::numericMatrix($samples);
        $this->featureCount = count($samples[0]);
        $this->fitted = true;
    }

    public function transform(array $samples): array
    {
        if (!$this->fitted) {
            throw new ModelNotTrainedException('PolynomialFeatures must be fitted before transform.');
        }

        Assert::numericMatrix($samples);

        $result = [];
        foreach ($samples as $sample) {
            Assert::sampleMatchesDimension($sample, $this->featureCount);
            $row = [];

            foreach ($sample as $value) {
                $row[] = (float) $value;
            }

            if ($this->degree >= 2) {
                for ($i = 0; $i < $this->featureCount; $i++) {
                    for ($j = $i; $j < $this->featureCount; $j++) {
                        $row[] = (float) $sample[$i] * (float) $sample[$j];
                    }
                }
            }

            $result[] = $row;
        }

        return $result;
    }
}
