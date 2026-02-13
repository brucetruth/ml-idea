<?php

declare(strict_types=1);

namespace ML\IDEA\Neural;

use ML\IDEA\Classifiers\AbstractClassifier;
use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Exceptions\ModelNotTrainedException;
use ML\IDEA\Support\Assert;

final class MLPBinaryClassifier extends AbstractClassifier
{
    /** @var array<int, array<int, float>> */
    private array $w1 = [];

    /** @var array<int, float> */
    private array $b1 = [];

    /** @var array<int, float> */
    private array $w2 = [];

    private float $b2 = 0.0;
    private int $featureCount = 0;
    private bool $trained = false;

    private int|float|string|bool $negativeClass = 0;
    private int|float|string|bool $positiveClass = 1;

    public function __construct(
        private readonly int $hiddenUnits = 8,
        private readonly int $epochs = 500,
        private readonly float $learningRate = 0.05,
        private readonly ?int $seed = 42,
    ) {
        if ($this->hiddenUnits <= 0 || $this->epochs <= 0 || $this->learningRate <= 0.0) {
            throw new InvalidArgumentException('hiddenUnits, epochs, learningRate must be positive.');
        }
    }

    public function train(array $samples, array $labels): void
    {
        Assert::numericMatrix($samples);
        Assert::matchingSampleLabelCount($samples, $labels);

        $classes = array_values(array_unique($labels, SORT_REGULAR));
        if (count($classes) !== 2) {
            throw new InvalidArgumentException('MLPBinaryClassifier supports binary classification only.');
        }

        $this->negativeClass = $classes[0];
        $this->positiveClass = $classes[1];

        $this->featureCount = count($samples[0]);
        if ($this->seed !== null) {
            mt_srand($this->seed);
        }

        $this->w1 = [];
        for ($h = 0; $h < $this->hiddenUnits; $h++) {
            $row = [];
            for ($j = 0; $j < $this->featureCount; $j++) {
                $row[] = (mt_rand() / mt_getrandmax() - 0.5) * 0.2;
            }
            $this->w1[] = $row;
        }

        $this->b1 = array_fill(0, $this->hiddenUnits, 0.0);
        $this->w2 = [];
        for ($h = 0; $h < $this->hiddenUnits; $h++) {
            $this->w2[] = (mt_rand() / mt_getrandmax() - 0.5) * 0.2;
        }
        $this->b2 = 0.0;

        for ($epoch = 0; $epoch < $this->epochs; $epoch++) {
            foreach ($samples as $i => $sample) {
                $x = array_map(static fn ($v): float => (float) $v, $sample);
                $y = $labels[$i] === $this->positiveClass ? 1.0 : 0.0;

                $z1 = [];
                $a1 = [];
                for ($h = 0; $h < $this->hiddenUnits; $h++) {
                    $sum = $this->b1[$h];
                    for ($j = 0; $j < $this->featureCount; $j++) {
                        $sum += $this->w1[$h][$j] * $x[$j];
                    }
                    $z1[$h] = $sum;
                    $a1[$h] = self::relu($sum);
                }

                $z2 = $this->b2;
                for ($h = 0; $h < $this->hiddenUnits; $h++) {
                    $z2 += $this->w2[$h] * $a1[$h];
                }
                $yHat = self::sigmoid($z2);

                $dz2 = $yHat - $y;
                for ($h = 0; $h < $this->hiddenUnits; $h++) {
                    $this->w2[$h] -= $this->learningRate * ($dz2 * $a1[$h]);
                }
                $this->b2 -= $this->learningRate * $dz2;

                for ($h = 0; $h < $this->hiddenUnits; $h++) {
                    $da1 = $dz2 * $this->w2[$h];
                    $dz1 = $da1 * self::reluPrime($z1[$h]);
                    for ($j = 0; $j < $this->featureCount; $j++) {
                        $this->w1[$h][$j] -= $this->learningRate * ($dz1 * $x[$j]);
                    }
                    $this->b1[$h] -= $this->learningRate * $dz1;
                }
            }
        }

        $this->trained = true;
    }

    public function predict(array $sample): int|float|string|bool
    {
        return $this->predictProbability($sample) >= 0.5 ? $this->positiveClass : $this->negativeClass;
    }

    public function predictProbability(array $sample): float
    {
        if (!$this->trained) {
            throw new ModelNotTrainedException('MLPBinaryClassifier has not been trained yet.');
        }

        Assert::sampleMatchesDimension($sample, $this->featureCount);
        $x = array_map(static fn ($v): float => (float) $v, $sample);

        $a1 = [];
        for ($h = 0; $h < $this->hiddenUnits; $h++) {
            $sum = $this->b1[$h];
            for ($j = 0; $j < $this->featureCount; $j++) {
                $sum += $this->w1[$h][$j] * $x[$j];
            }
            $a1[$h] = self::relu($sum);
        }

        $z2 = $this->b2;
        for ($h = 0; $h < $this->hiddenUnits; $h++) {
            $z2 += $this->w2[$h] * $a1[$h];
        }

        return self::sigmoid($z2);
    }

    private static function relu(float $x): float
    {
        return $x > 0.0 ? $x : 0.0;
    }

    private static function reluPrime(float $x): float
    {
        return $x > 0.0 ? 1.0 : 0.0;
    }

    private static function sigmoid(float $x): float
    {
        if ($x >= 0.0) {
            $z = exp(-$x);
            return 1.0 / (1.0 + $z);
        }

        $z = exp($x);
        return $z / (1.0 + $z);
    }
}
