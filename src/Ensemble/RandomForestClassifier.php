<?php

declare(strict_types=1);

namespace ML\IDEA\Ensemble;

use ML\IDEA\Classifiers\AbstractClassifier;
use ML\IDEA\Contracts\PersistableModelInterface;
use ML\IDEA\Contracts\ProbabilisticClassifierInterface;
use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Exceptions\ModelNotTrainedException;
use ML\IDEA\Support\Assert;

final class RandomForestClassifier extends AbstractClassifier implements PersistableModelInterface, ProbabilisticClassifierInterface
{
    /** @var array<int, array<string, mixed>> */
    private array $trees = [];

    private int $featureCount = 0;
    private bool $trained = false;

    /** @var array<int, int|float|string|bool> */
    private array $classes = [];

    public function __construct(
        private readonly int $nEstimators = 50,
        private readonly int $maxDepth = 3,
        private readonly int $minSamplesSplit = 2,
        private readonly ?int $maxFeatures = null,
        private readonly ?int $seed = 42,
    ) {
        if ($this->nEstimators <= 0) {
            throw new InvalidArgumentException('nEstimators must be positive.');
        }
        if ($this->maxDepth <= 0) {
            throw new InvalidArgumentException('maxDepth must be positive.');
        }
    }

    public function train(array $samples, array $labels): void
    {
        Assert::numericMatrix($samples);
        Assert::matchingSampleLabelCount($samples, $labels);

        $nSamples = count($samples);
        $this->featureCount = count($samples[0]);
        $this->classes = array_values(array_unique($labels, SORT_REGULAR));
        $maxFeatures = $this->maxFeatures ?? max(1, (int) floor(sqrt($this->featureCount)));
        $maxFeatures = min($maxFeatures, $this->featureCount);

        if ($this->seed !== null) {
            mt_srand($this->seed);
        }

        $this->trees = [];
        for ($t = 0; $t < $this->nEstimators; $t++) {
            $bootstrapSamples = [];
            $bootstrapLabels = [];
            for ($i = 0; $i < $nSamples; $i++) {
                $idx = mt_rand(0, $nSamples - 1);
                $bootstrapSamples[] = $samples[$idx];
                $bootstrapLabels[] = $labels[$idx];
            }

            $features = range(0, $this->featureCount - 1);
            shuffle($features);
            $features = array_slice($features, 0, $maxFeatures);

            $tree = new DecisionTree($this->maxDepth, $this->minSamplesSplit, 'classification', $this->seed);
            $tree->fit($bootstrapSamples, $bootstrapLabels, $features);
            $this->trees[] = $tree->toArray();
        }

        $this->trained = true;
    }

    public function predict(array $sample): int|float|string|bool
    {
        $proba = $this->predictProba($sample);
        arsort($proba);

        return array_key_first($proba);
    }

    public function predictProba(array $sample): array
    {
        if (!$this->trained) {
            throw new ModelNotTrainedException('RandomForestClassifier has not been trained yet.');
        }

        Assert::sampleMatchesDimension($sample, $this->featureCount);

        $counts = array_fill_keys(array_map(fn ($c) => $this->labelKey($c), $this->classes), 0);
        foreach ($this->trees as $treeState) {
            $tree = DecisionTree::fromArray($treeState);
            $prediction = $tree->predict($sample);
            $key = $this->labelKey($prediction);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        $total = max(1, array_sum($counts));
        $proba = [];
        foreach ($this->classes as $class) {
            $proba[$class] = ($counts[$this->labelKey($class)] ?? 0) / $total;
        }

        return $proba;
    }

    public function predictProbaBatch(array $samples): array
    {
        return array_map(fn (array $sample): array => $this->predictProba($sample), $samples);
    }

    public function toArray(): array
    {
        return [
            'nEstimators' => $this->nEstimators,
            'maxDepth' => $this->maxDepth,
            'minSamplesSplit' => $this->minSamplesSplit,
            'maxFeatures' => $this->maxFeatures,
            'seed' => $this->seed,
            'featureCount' => $this->featureCount,
            'classes' => $this->classes,
            'trees' => $this->trees,
            'trained' => $this->trained,
        ];
    }

    public static function fromArray(array $data): static
    {
        $model = new self(
            (int) ($data['nEstimators'] ?? 50),
            (int) ($data['maxDepth'] ?? 3),
            (int) ($data['minSamplesSplit'] ?? 2),
            isset($data['maxFeatures']) ? (int) $data['maxFeatures'] : null,
            isset($data['seed']) ? (int) $data['seed'] : null,
        );
        $model->featureCount = (int) ($data['featureCount'] ?? 0);
        $model->classes = is_array($data['classes'] ?? null) ? $data['classes'] : [];
        $model->trees = is_array($data['trees'] ?? null) ? $data['trees'] : [];
        $model->trained = (bool) ($data['trained'] ?? false);

        return $model;
    }

    private function labelKey(int|float|string|bool $label): string
    {
        return get_debug_type($label) . ':' . json_encode($label, JSON_THROW_ON_ERROR);
    }
}
