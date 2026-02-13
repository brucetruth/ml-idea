<?php

declare(strict_types=1);

namespace ML\IDEA\Preprocessing;

use ML\IDEA\Exceptions\ModelNotTrainedException;
use ML\IDEA\Support\Assert;

final class SimpleImputer extends AbstractTransformer
{
    /** @var array<int, float> */
    private array $fillValues = [];

    private int $featureCount = 0;
    private bool $fitted = false;

    public function __construct(private readonly string $strategy = 'mean')
    {
    }

    public function fit(array $samples): void
    {
        Assert::numericMatrix($samples);

        $this->featureCount = count($samples[0]);
        $columnValues = array_fill(0, $this->featureCount, []);

        foreach ($samples as $sample) {
            foreach ($sample as $j => $value) {
                $x = (float) $value;
                if (!is_nan($x)) {
                    $columnValues[$j][] = $x;
                }
            }
        }

        $this->fillValues = [];
        foreach ($columnValues as $values) {
            if ($values === []) {
                $this->fillValues[] = 0.0;
                continue;
            }

            if ($this->strategy === 'median') {
                sort($values);
                $n = count($values);
                $mid = intdiv($n, 2);
                $fill = $n % 2 === 0 ? ($values[$mid - 1] + $values[$mid]) / 2.0 : $values[$mid];
                $this->fillValues[] = $fill;
                continue;
            }

            $this->fillValues[] = array_sum($values) / count($values);
        }

        $this->fitted = true;
    }

    public function transform(array $samples): array
    {
        if (!$this->fitted) {
            throw new ModelNotTrainedException('SimpleImputer must be fitted before transform.');
        }

        Assert::numericMatrix($samples);

        $result = [];
        foreach ($samples as $sample) {
            Assert::sampleMatchesDimension($sample, $this->featureCount);
            $row = [];
            foreach ($sample as $j => $value) {
                $x = (float) $value;
                $row[] = is_nan($x) ? $this->fillValues[$j] : $x;
            }
            $result[] = $row;
        }

        return $result;
    }
}
