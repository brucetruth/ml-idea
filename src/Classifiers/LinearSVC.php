<?php

declare(strict_types=1);

namespace ML\IDEA\Classifiers;

use ML\IDEA\Contracts\PersistableModelInterface;
use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Exceptions\ModelNotTrainedException;
use ML\IDEA\Math\LinearAlgebra;
use ML\IDEA\Support\Assert;

/** Linear SVM (binary) trained with SGD on hinge loss. */
final class LinearSVC extends AbstractClassifier implements PersistableModelInterface
{
    /** @var array<int, float> */
    private array $weights = [];

    private float $bias = 0.0;
    private int $featureCount = 0;
    private bool $trained = false;
    private int|float|string|bool $positiveClass = 1;
    private int|float|string|bool $negativeClass = 0;

    public function __construct(
        private readonly float $learningRate = 0.01,
        private readonly int $iterations = 2000,
        private readonly float $l2Penalty = 0.0001,
        private readonly int $batchSize = 32,
    ) {
        if ($learningRate <= 0.0) {
            throw new InvalidArgumentException('learningRate must be greater than 0.');
        }
    }

    public function train(array $samples, array $labels): void
    {
        Assert::numericMatrix($samples);
        Assert::matchingSampleLabelCount($samples, $labels);

        $classes = array_values(array_unique($labels, SORT_REGULAR));
        if (count($classes) !== 2) {
            throw new InvalidArgumentException('LinearSVC currently supports binary classification.');
        }

        $this->negativeClass = $classes[0];
        $this->positiveClass = $classes[1];
        $this->featureCount = count($samples[0]);
        $this->weights = array_fill(0, $this->featureCount, 0.0);
        $this->bias = 0.0;

        $n = count($samples);
        $batchSize = max(1, min($this->batchSize, $n));

        for ($iter = 0; $iter < $this->iterations; $iter++) {
            $indices = range(0, $n - 1);
            shuffle($indices);

            for ($start = 0; $start < $n; $start += $batchSize) {
                $batch = array_slice($indices, $start, $batchSize);
                foreach ($batch as $i) {
                    $y = $labels[$i] === $this->positiveClass ? 1.0 : -1.0;
                    $score = LinearAlgebra::dot($samples[$i], $this->weights) + $this->bias;
                    $lossGradient = ($y * $score) >= 1.0 ? 0.0 : -$y;

                    foreach ($this->weights as $j => $weight) {
                        $this->weights[$j] -= $this->learningRate * (($lossGradient * (float) $samples[$i][$j]) + ($this->l2Penalty * $weight));
                    }
                    $this->bias -= $this->learningRate * $lossGradient;
                }
            }
        }

        $this->trained = true;
    }

    public function predict(array $sample): int|float|string|bool
    {
        if (!$this->trained) {
            throw new ModelNotTrainedException('LinearSVC has not been trained yet.');
        }

        $score = LinearAlgebra::dot($sample, $this->weights) + $this->bias;

        return $score >= 0.0 ? $this->positiveClass : $this->negativeClass;
    }

    public function toArray(): array
    {
        return [
            'weights' => $this->weights,
            'bias' => $this->bias,
            'featureCount' => $this->featureCount,
            'trained' => $this->trained,
            'positiveClass' => $this->positiveClass,
            'negativeClass' => $this->negativeClass,
            'learningRate' => $this->learningRate,
            'iterations' => $this->iterations,
            'l2Penalty' => $this->l2Penalty,
            'batchSize' => $this->batchSize,
        ];
    }

    public static function fromArray(array $data): static
    {
        $model = new self(
            (float) ($data['learningRate'] ?? 0.01),
            (int) ($data['iterations'] ?? 2000),
            (float) ($data['l2Penalty'] ?? 0.0001),
            (int) ($data['batchSize'] ?? 32),
        );
        $model->weights = array_map('floatval', is_array($data['weights'] ?? null) ? $data['weights'] : []);
        $model->bias = (float) ($data['bias'] ?? 0.0);
        $model->featureCount = (int) ($data['featureCount'] ?? 0);
        $model->trained = (bool) ($data['trained'] ?? false);
        $model->positiveClass = $data['positiveClass'] ?? 1;
        $model->negativeClass = $data['negativeClass'] ?? 0;

        return $model;
    }
}
