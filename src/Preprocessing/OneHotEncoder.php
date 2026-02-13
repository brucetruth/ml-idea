<?php

declare(strict_types=1);

namespace ML\IDEA\Preprocessing;

use ML\IDEA\Exceptions\ModelNotTrainedException;

final class OneHotEncoder
{
    /** @var array<int, array<string, int>> */
    private array $categories = [];

    private bool $fitted = false;

    /**
     * @param array<int, array<int, string|int|float|bool>> $samples
     */
    public function fit(array $samples): void
    {
        if ($samples === []) {
            throw new \InvalidArgumentException('Samples cannot be empty.');
        }

        $featureCount = count($samples[0]);
        $this->categories = array_fill(0, $featureCount, []);

        foreach ($samples as $sample) {
            if (count($sample) !== $featureCount) {
                throw new \InvalidArgumentException('All samples must have same feature count.');
            }

            foreach ($sample as $j => $value) {
                $key = self::toKey($value);
                if (!isset($this->categories[$j][$key])) {
                    $this->categories[$j][$key] = count($this->categories[$j]);
                }
            }
        }

        $this->fitted = true;
    }

    /**
     * @param array<int, array<int, string|int|float|bool>> $samples
     * @return array<int, array<int, float>>
     */
    public function transform(array $samples): array
    {
        if (!$this->fitted) {
            throw new ModelNotTrainedException('OneHotEncoder must be fitted before transform.');
        }

        $encoded = [];
        foreach ($samples as $sample) {
            $row = [];
            foreach ($sample as $j => $value) {
                $numCategories = count($this->categories[$j]);
                $vector = array_fill(0, $numCategories, 0.0);
                $key = self::toKey($value);
                if (isset($this->categories[$j][$key])) {
                    $vector[$this->categories[$j][$key]] = 1.0;
                }
                $row = array_merge($row, $vector);
            }
            $encoded[] = $row;
        }

        return $encoded;
    }

    /**
     * @param array<int, array<int, string|int|float|bool>> $samples
     * @return array<int, array<int, float>>
     */
    public function fitTransform(array $samples): array
    {
        $this->fit($samples);
        return $this->transform($samples);
    }

    private static function toKey(string|int|float|bool $value): string
    {
        return get_debug_type($value) . ':' . json_encode($value, JSON_THROW_ON_ERROR);
    }
}
