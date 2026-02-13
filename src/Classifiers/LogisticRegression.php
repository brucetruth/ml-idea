<?php

declare(strict_types=1);

namespace ML\IDEA\Classifiers;

use ML\IDEA\Contracts\PersistableModelInterface;
use ML\IDEA\Contracts\ProbabilisticClassifierInterface;
use ML\IDEA\Contracts\OnlineLearnerInterface;
use ML\IDEA\Contracts\SerializableModelInterface;
use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Exceptions\ModelNotTrainedException;
use ML\IDEA\Math\LinearAlgebra;
use ML\IDEA\Model\ModelSerializer;
use ML\IDEA\Support\Assert;

final class LogisticRegression extends AbstractClassifier implements PersistableModelInterface, SerializableModelInterface, ProbabilisticClassifierInterface, OnlineLearnerInterface
{
    /** @var array<int, float> */
    private array $weights = [];

    private float $bias = 0.0;
    private int $featureCount = 0;
    private bool $trained = false;

    private int|float|string|bool $negativeClass = 0;
    private int|float|string|bool $positiveClass = 1;

    public function __construct(
        private readonly float $learningRate = 0.1,
        private readonly int $iterations = 1000,
        private readonly float $l2Penalty = 0.0,
    ) {
        if ($learningRate <= 0.0) {
            throw new InvalidArgumentException('learningRate must be greater than 0.');
        }

        Assert::positiveInt($iterations, 'iterations');

        if ($l2Penalty < 0.0) {
            throw new InvalidArgumentException('l2Penalty cannot be negative.');
        }
    }

    public function train(array $samples, array $labels): void
    {
        Assert::numericMatrix($samples);
        Assert::matchingSampleLabelCount($samples, $labels);

        $classes = array_values(array_unique($labels, SORT_REGULAR));
        if (count($classes) !== 2) {
            throw new InvalidArgumentException('LogisticRegression currently supports binary classification only.');
        }

        $this->negativeClass = $classes[0];
        $this->positiveClass = $classes[1];

        $this->featureCount = count($samples[0]);
        $this->weights = array_fill(0, $this->featureCount, 0.0);
        $this->bias = 0.0;

        $sampleCount = count($samples);

        for ($iter = 0; $iter < $this->iterations; $iter++) {
            $gradientW = array_fill(0, $this->featureCount, 0.0);
            $gradientB = 0.0;

            foreach ($samples as $i => $sample) {
                $y = $labels[$i] === $this->positiveClass ? 1.0 : 0.0;
                $prediction = LinearAlgebra::sigmoid(LinearAlgebra::dot($sample, $this->weights) + $this->bias);
                $error = $prediction - $y;

                foreach ($sample as $j => $value) {
                    $gradientW[$j] += $error * (float) $value;
                }

                $gradientB += $error;
            }

            foreach ($this->weights as $j => $weight) {
                $gradient = ($gradientW[$j] / $sampleCount) + ($this->l2Penalty * $weight);
                $this->weights[$j] -= $this->learningRate * $gradient;
            }

            $this->bias -= $this->learningRate * ($gradientB / $sampleCount);
        }

        $this->trained = true;
    }

    public function predict(array $sample): int|float|string|bool
    {
        if (!$this->trained) {
            throw new ModelNotTrainedException('LogisticRegression has not been trained yet.');
        }

        Assert::sampleMatchesDimension($sample, $this->featureCount);

        return $this->predictProbability($sample) >= 0.5 ? $this->positiveClass : $this->negativeClass;
    }

    public function predictProba(array $sample): array
    {
        $p1 = $this->predictProbability($sample);
        return [
            $this->negativeClass => 1.0 - $p1,
            $this->positiveClass => $p1,
        ];
    }

    public function predictProbaBatch(array $samples): array
    {
        $result = [];
        foreach ($samples as $sample) {
            $result[] = $this->predictProba($sample);
        }

        return $result;
    }

    /**
     * @param array<int, float|int> $sample
     */
    public function predictProbability(array $sample): float
    {
        if (!$this->trained) {
            throw new ModelNotTrainedException('LogisticRegression has not been trained yet.');
        }

        Assert::sampleMatchesDimension($sample, $this->featureCount);

        return LinearAlgebra::sigmoid(LinearAlgebra::dot($sample, $this->weights) + $this->bias);
    }

    public function toArray(): array
    {
        return [
            'weights' => $this->weights,
            'bias' => $this->bias,
            'featureCount' => $this->featureCount,
            'trained' => $this->trained,
            'negativeClass' => $this->negativeClass,
            'positiveClass' => $this->positiveClass,
            'learningRate' => $this->learningRate,
            'iterations' => $this->iterations,
            'l2Penalty' => $this->l2Penalty,
        ];
    }

    public static function fromArray(array $data): static
    {
        $model = new self(
            (float) ($data['learningRate'] ?? 0.1),
            (int) ($data['iterations'] ?? 1000),
            (float) ($data['l2Penalty'] ?? 0.0),
        );

        $model->weights = array_map(static fn ($value): float => (float) $value, $data['weights'] ?? []);
        $model->bias = (float) ($data['bias'] ?? 0.0);
        $model->featureCount = (int) ($data['featureCount'] ?? 0);
        $model->trained = (bool) ($data['trained'] ?? false);
        $model->negativeClass = $data['negativeClass'] ?? 0;
        $model->positiveClass = $data['positiveClass'] ?? 1;

        return $model;
    }

    public function partialFit(array $samples, array $targets): void
    {
        $this->train($samples, $targets);
    }

    public function save(string $path): void
    {
        ModelSerializer::save($this, $path);
    }

    public static function load(string $path): static
    {
        $model = ModelSerializer::load($path);
        if (!$model instanceof static) {
            throw new InvalidArgumentException('Serialized model type mismatch for LogisticRegression.');
        }

        return $model;
    }
}
