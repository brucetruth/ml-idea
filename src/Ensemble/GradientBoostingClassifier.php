<?php

declare(strict_types=1);

namespace ML\IDEA\Ensemble;

use ML\IDEA\Classifiers\AbstractClassifier;
use ML\IDEA\Contracts\PersistableModelInterface;
use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Exceptions\ModelNotTrainedException;
use ML\IDEA\Support\Assert;

/** Gradient boosting for binary classification using shallow regression trees. */
final class GradientBoostingClassifier extends AbstractClassifier implements PersistableModelInterface
{
    /** @var array<int, array<string, mixed>> */
    private array $stages = [];

    private int $featureCount = 0;
    private bool $trained = false;
    private int|float|string|bool $positiveClass = 1;
    private int|float|string|bool $negativeClass = 0;

    public function __construct(
        private readonly int $nEstimators = 100,
        private readonly float $learningRate = 0.1,
        private readonly int $maxDepth = 2,
        private readonly ?int $seed = 42,
    ) {
        if ($this->nEstimators <= 0) {
            throw new InvalidArgumentException('nEstimators must be positive.');
        }
        if ($this->learningRate <= 0.0) {
            throw new InvalidArgumentException('learningRate must be positive.');
        }
    }

    public function train(array $samples, array $labels): void
    {
        Assert::numericMatrix($samples);
        Assert::matchingSampleLabelCount($samples, $labels);

        $classes = array_values(array_unique($labels, SORT_REGULAR));
        if (count($classes) !== 2) {
            throw new InvalidArgumentException('GradientBoostingClassifier currently supports binary classification.');
        }

        $this->negativeClass = $classes[0];
        $this->positiveClass = $classes[1];
        $this->featureCount = count($samples[0]);
        $this->stages = [];

        $n = count($samples);
        $rawScores = array_fill(0, $n, 0.0);

        if ($this->seed !== null) {
            mt_srand($this->seed);
        }

        for ($m = 0; $m < $this->nEstimators; $m++) {
            $pseudo = [];
            foreach ($labels as $i => $label) {
                $y = $label === $this->positiveClass ? 1.0 : 0.0;
                $p = self::sigmoid($rawScores[$i]);
                $pseudo[] = $y - $p;
            }

            $tree = new DecisionTree($this->maxDepth, 2, 'regression', $this->seed);
            $tree->fit($samples, $pseudo);
            $this->stages[] = $tree->toArray();

            foreach ($samples as $i => $sample) {
                $rawScores[$i] += $this->learningRate * (float) $tree->predict($sample);
            }
        }

        $this->trained = true;
    }

    public function predict(array $sample): int|float|string|bool
    {
        $score = $this->rawScore($sample);

        return self::sigmoid($score) >= 0.5 ? $this->positiveClass : $this->negativeClass;
    }

    public function toArray(): array
    {
        return [
            'nEstimators' => $this->nEstimators,
            'learningRate' => $this->learningRate,
            'maxDepth' => $this->maxDepth,
            'seed' => $this->seed,
            'featureCount' => $this->featureCount,
            'positiveClass' => $this->positiveClass,
            'negativeClass' => $this->negativeClass,
            'stages' => $this->stages,
            'trained' => $this->trained,
        ];
    }

    public static function fromArray(array $data): static
    {
        $model = new self(
            (int) ($data['nEstimators'] ?? 100),
            (float) ($data['learningRate'] ?? 0.1),
            (int) ($data['maxDepth'] ?? 2),
            isset($data['seed']) ? (int) $data['seed'] : null,
        );
        $model->featureCount = (int) ($data['featureCount'] ?? 0);
        $model->positiveClass = $data['positiveClass'] ?? 1;
        $model->negativeClass = $data['negativeClass'] ?? 0;
        $model->stages = is_array($data['stages'] ?? null) ? $data['stages'] : [];
        $model->trained = (bool) ($data['trained'] ?? false);

        return $model;
    }

    private function rawScore(array $sample): float
    {
        if (!$this->trained) {
            throw new ModelNotTrainedException('GradientBoostingClassifier has not been trained yet.');
        }

        Assert::sampleMatchesDimension($sample, $this->featureCount);

        $score = 0.0;
        foreach ($this->stages as $stage) {
            $tree = DecisionTree::fromArray($stage);
            $score += $this->learningRate * (float) $tree->predict($sample);
        }

        return $score;
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
