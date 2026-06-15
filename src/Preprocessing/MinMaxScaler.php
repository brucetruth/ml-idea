<?php

declare(strict_types=1);

namespace ML\IDEA\Preprocessing;

use ML\IDEA\Contracts\PersistableModelInterface;
use ML\IDEA\Exceptions\ModelNotTrainedException;
use ML\IDEA\Support\Assert;

final class MinMaxScaler extends AbstractTransformer implements PersistableModelInterface
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

    public function toArray(): array
    {
        return [
            'mins' => $this->mins,
            'maxs' => $this->maxs,
            'featureCount' => $this->featureCount,
            'fitted' => $this->fitted,
        ];
    }

    public static function fromArray(array $data): static
    {
        $scaler = new self();
        $scaler->mins = array_map('floatval', is_array($data['mins'] ?? null) ? $data['mins'] : []);
        $scaler->maxs = array_map('floatval', is_array($data['maxs'] ?? null) ? $data['maxs'] : []);
        $scaler->featureCount = (int) ($data['featureCount'] ?? 0);
        $scaler->fitted = (bool) ($data['fitted'] ?? false);

        return $scaler;
    }
}
