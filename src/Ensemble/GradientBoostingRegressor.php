<?php

declare(strict_types=1);

namespace ML\IDEA\Ensemble;

use ML\IDEA\Contracts\PersistableModelInterface;
use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Exceptions\ModelNotTrainedException;
use ML\IDEA\Regression\AbstractRegressor;
use ML\IDEA\Support\Assert;

/** Gradient boosting for regression using shallow regression trees. */
final class GradientBoostingRegressor extends AbstractRegressor implements PersistableModelInterface
{
    /** @var array<int, array<string, mixed>> */
    private array $stages = [];

    private int $featureCount = 0;
    private bool $trained = false;

    public function __construct(
        private readonly int $nEstimators = 100,
        private readonly float $learningRate = 0.1,
        private readonly int $maxDepth = 3,
        private readonly ?int $seed = 42,
    ) {
        if ($this->nEstimators <= 0) {
            throw new InvalidArgumentException('nEstimators must be positive.');
        }
        if ($this->learningRate <= 0.0) {
            throw new InvalidArgumentException('learningRate must be positive.');
        }
    }

    public function train(array $samples, array $targets): void
    {
        Assert::numericMatrix($samples);
        if ($targets === [] || count($samples) !== count($targets)) {
            throw new InvalidArgumentException('Targets must be non-empty and match sample count.');
        }

        $this->featureCount = count($samples[0]);
        $this->stages = [];

        $n = count($samples);
        $predictions = array_fill(0, $n, 0.0);

        if ($this->seed !== null) {
            mt_srand($this->seed);
        }

        for ($m = 0; $m < $this->nEstimators; $m++) {
            $residuals = [];
            foreach ($targets as $i => $target) {
                $residuals[] = (float) $target - $predictions[$i];
            }

            $tree = new DecisionTree($this->maxDepth, 2, 'regression', $this->seed);
            $tree->fit($samples, $residuals);
            $this->stages[] = $tree->toArray();

            foreach ($samples as $i => $sample) {
                $predictions[$i] += $this->learningRate * (float) $tree->predict($sample);
            }
        }

        $this->trained = true;
    }

    public function predict(array $sample): float
    {
        if (!$this->trained) {
            throw new ModelNotTrainedException('GradientBoostingRegressor has not been trained yet.');
        }

        Assert::sampleMatchesDimension($sample, $this->featureCount);

        $score = 0.0;
        foreach ($this->stages as $stage) {
            $tree = DecisionTree::fromArray($stage);
            $score += $this->learningRate * (float) $tree->predict($sample);
        }

        return $score;
    }

    public function toArray(): array
    {
        return [
            'nEstimators' => $this->nEstimators,
            'learningRate' => $this->learningRate,
            'maxDepth' => $this->maxDepth,
            'seed' => $this->seed,
            'featureCount' => $this->featureCount,
            'stages' => $this->stages,
            'trained' => $this->trained,
        ];
    }

    public static function fromArray(array $data): static
    {
        $model = new self(
            (int) ($data['nEstimators'] ?? 100),
            (float) ($data['learningRate'] ?? 0.1),
            (int) ($data['maxDepth'] ?? 3),
            isset($data['seed']) ? (int) $data['seed'] : null,
        );
        $model->featureCount = (int) ($data['featureCount'] ?? 0);
        $model->stages = is_array($data['stages'] ?? null) ? $data['stages'] : [];
        $model->trained = (bool) ($data['trained'] ?? false);

        return $model;
    }
}
