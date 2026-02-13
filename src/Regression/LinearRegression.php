<?php

declare(strict_types=1);

namespace ML\IDEA\Regression;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Exceptions\ModelNotTrainedException;
use ML\IDEA\Math\LinearAlgebra;
use ML\IDEA\Support\Assert;

final class LinearRegression extends AbstractRegressor
{
    /** @var array<int, float> */
    private array $weights = [];

    private float $bias = 0.0;
    private int $featureCount = 0;
    private bool $trained = false;

    public function __construct(
        private readonly float $learningRate = 0.01,
        private readonly int $iterations = 2000,
        private readonly float $l2Penalty = 0.0,
    ) {
        if ($this->learningRate <= 0.0) {
            throw new InvalidArgumentException('learningRate must be greater than 0.');
        }

        Assert::positiveInt($this->iterations, 'iterations');

        if ($this->l2Penalty < 0.0) {
            throw new InvalidArgumentException('l2Penalty cannot be negative.');
        }
    }

    public function train(array $samples, array $targets): void
    {
        Assert::numericMatrix($samples);
        if ($targets === [] || count($targets) !== count($samples)) {
            throw new InvalidArgumentException('Targets must be non-empty and match sample count.');
        }

        $this->featureCount = count($samples[0]);
        $this->weights = array_fill(0, $this->featureCount, 0.0);
        $this->bias = 0.0;

        $n = count($samples);
        for ($iter = 0; $iter < $this->iterations; $iter++) {
            $gradientW = array_fill(0, $this->featureCount, 0.0);
            $gradientB = 0.0;

            foreach ($samples as $i => $sample) {
                $prediction = LinearAlgebra::dot($sample, $this->weights) + $this->bias;
                $error = $prediction - (float) $targets[$i];

                foreach ($sample as $j => $value) {
                    $gradientW[$j] += $error * (float) $value;
                }
                $gradientB += $error;
            }

            foreach ($this->weights as $j => $weight) {
                $gradient = ($gradientW[$j] / $n) + ($this->l2Penalty * $weight);
                $this->weights[$j] -= $this->learningRate * $gradient;
            }

            $this->bias -= $this->learningRate * ($gradientB / $n);
        }

        $this->trained = true;
    }

    public function predict(array $sample): float
    {
        if (!$this->trained) {
            throw new ModelNotTrainedException('LinearRegression has not been trained yet.');
        }

        Assert::sampleMatchesDimension($sample, $this->featureCount);

        return LinearAlgebra::dot($sample, $this->weights) + $this->bias;
    }
}
