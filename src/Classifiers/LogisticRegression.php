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
    /** @var array<int, array<int, float>> */
    private array $weightsByClass = [];

    /** @var array<int, float> */
    private array $biasByClass = [];
    private int $featureCount = 0;
    private bool $trained = false;

    /** @var array<int, int|float|string|bool> */
    private array $classes = [];

    public function __construct(
        private readonly float $learningRate = 0.1,
        private readonly int $iterations = 1000,
        private readonly float $l2Penalty = 0.0,
        private readonly int $batchSize = 0,
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
        if (count($classes) < 2) {
            throw new InvalidArgumentException('LogisticRegression requires at least 2 distinct classes.');
        }

        $this->classes = $classes;

        $this->featureCount = count($samples[0]);
        $this->weightsByClass = [];
        $this->biasByClass = [];

        foreach ($this->classes as $classIndex => $classValue) {
            $this->weightsByClass[$classIndex] = array_fill(0, $this->featureCount, 0.0);
            $this->biasByClass[$classIndex] = 0.0;
        }

        $sampleCount = count($samples);
        $classCount = count($this->classes);
        $batchSize = $this->batchSize > 0 ? min($this->batchSize, $sampleCount) : $sampleCount;

        for ($iter = 0; $iter < $this->iterations; $iter++) {
            $indices = range(0, $sampleCount - 1);
            if ($batchSize < $sampleCount) {
                shuffle($indices);
            }

            for ($start = 0; $start < $sampleCount; $start += $batchSize) {
                $batch = array_slice($indices, $start, $batchSize);
                $this->applyBatch($samples, $labels, $batch, $classCount, $sampleCount);
            }
        }

        $this->trained = true;
    }

    public function predict(array $sample): int|float|string|bool
    {
        if (!$this->trained) {
            throw new ModelNotTrainedException('LogisticRegression has not been trained yet.');
        }

        Assert::sampleMatchesDimension($sample, $this->featureCount);

        $probabilities = $this->predictProba($sample);
        $bestClass = null;
        $bestProbability = -INF;

        foreach ($probabilities as $class => $probability) {
            if ($probability > $bestProbability) {
                $bestProbability = $probability;
                $bestClass = $class;
            }
        }

        if ($bestClass === null) {
            throw new ModelNotTrainedException('LogisticRegression has not been trained yet.');
        }

        return $bestClass;
    }

    public function predictProba(array $sample): array
    {
        if (!$this->trained) {
            throw new ModelNotTrainedException('LogisticRegression has not been trained yet.');
        }

        Assert::sampleMatchesDimension($sample, $this->featureCount);

        $scores = [];
        foreach ($this->classes as $k => $_class) {
            $scores[$k] = LinearAlgebra::dot($sample, $this->weightsByClass[$k]) + $this->biasByClass[$k];
        }

        $indexedProbabilities = $this->softmax($scores);
        $result = [];
        foreach ($this->classes as $k => $classValue) {
            $result[$classValue] = $indexedProbabilities[$k];
        }

        return $result;
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
        $probabilities = $this->predictProba($sample);
        if (count($this->classes) !== 2) {
            throw new InvalidArgumentException('predictProbability is only available for binary LogisticRegression models. Use predictProba for multiclass models.');
        }

        $positiveClass = $this->classes[1];
        return (float) ($probabilities[$positiveClass] ?? 0.0);
    }

    public function toArray(): array
    {
        return [
            'weightsByClass' => $this->weightsByClass,
            'biasByClass' => $this->biasByClass,
            'featureCount' => $this->featureCount,
            'trained' => $this->trained,
            'classes' => $this->classes,
            'learningRate' => $this->learningRate,
            'iterations' => $this->iterations,
            'l2Penalty' => $this->l2Penalty,
            'batchSize' => $this->batchSize,
        ];
    }

    public static function fromArray(array $data): static
    {
        $model = new self(
            (float) ($data['learningRate'] ?? 0.1),
            (int) ($data['iterations'] ?? 1000),
            (float) ($data['l2Penalty'] ?? 0.0),
        );

        $weightsByClass = $data['weightsByClass'] ?? null;
        $biasByClass = $data['biasByClass'] ?? null;

        if (is_array($weightsByClass) && is_array($biasByClass)) {
            $model->weightsByClass = [];
            foreach ($weightsByClass as $classIndex => $weights) {
                $model->weightsByClass[(int) $classIndex] = array_map(static fn ($value): float => (float) $value, is_array($weights) ? $weights : []);
            }
            $model->biasByClass = array_map(static fn ($value): float => (float) $value, $biasByClass);
            $model->classes = is_array($data['classes'] ?? null) ? array_values($data['classes']) : [];
        } else {
            // Backward compatibility with binary-only serialized payloads
            $legacyWeights = array_map(static fn ($value): float => (float) $value, $data['weights'] ?? []);
            $legacyBias = (float) ($data['bias'] ?? 0.0);
            $negativeClass = $data['negativeClass'] ?? 0;
            $positiveClass = $data['positiveClass'] ?? 1;

            $model->classes = [$negativeClass, $positiveClass];
            $model->weightsByClass = [
                0 => array_fill(0, count($legacyWeights), 0.0),
                1 => $legacyWeights,
            ];
            $model->biasByClass = [0 => 0.0, 1 => $legacyBias];
        }

        $model->featureCount = (int) ($data['featureCount'] ?? 0);
        $model->trained = (bool) ($data['trained'] ?? false);

        return $model;
    }

    /**
     * @param array<int, array<int, float|int>> $samples
     * @param array<int, int|float|string|bool> $labels
     * @param array<int, int> $batch
     */
    private function applyBatch(array $samples, array $labels, array $batch, int $classCount, int $sampleCount): void
    {
        $batchCount = max(1, count($batch));
        $gradientWByClass = [];
        $gradientBByClass = array_fill(0, $classCount, 0.0);
        for ($k = 0; $k < $classCount; $k++) {
            $gradientWByClass[$k] = array_fill(0, $this->featureCount, 0.0);
        }

        foreach ($batch as $i) {
            $sample = $samples[$i];
            $scores = [];
            for ($k = 0; $k < $classCount; $k++) {
                $scores[$k] = LinearAlgebra::dot($sample, $this->weightsByClass[$k]) + $this->biasByClass[$k];
            }

            $probabilities = $this->softmax($scores);

            for ($k = 0; $k < $classCount; $k++) {
                $y = $labels[$i] === $this->classes[$k] ? 1.0 : 0.0;
                $error = $probabilities[$k] - $y;

                foreach ($sample as $j => $value) {
                    $gradientWByClass[$k][$j] += $error * (float) $value;
                }

                $gradientBByClass[$k] += $error;
            }
        }

        for ($k = 0; $k < $classCount; $k++) {
            foreach ($this->weightsByClass[$k] as $j => $weight) {
                $gradient = ($gradientWByClass[$k][$j] / $batchCount) + ($this->l2Penalty * $weight);
                $this->weightsByClass[$k][$j] -= $this->learningRate * $gradient;
            }

            $this->biasByClass[$k] -= $this->learningRate * ($gradientBByClass[$k] / $batchCount);
        }
    }

    /**
     * @param array<int, float> $scores
     * @return array<int, float>
     */
    private function softmax(array $scores): array
    {
        $maxScore = max($scores);
        $expScores = [];
        $sum = 0.0;

        foreach ($scores as $k => $score) {
            $value = exp($score - $maxScore);
            $expScores[$k] = $value;
            $sum += $value;
        }

        if ($sum <= 0.0) {
            $count = count($scores);
            return array_fill(0, $count, 1.0 / max(1, $count));
        }

        foreach ($expScores as $k => $value) {
            $expScores[$k] = $value / $sum;
        }

        return $expScores;
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
