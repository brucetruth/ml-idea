<?php

declare(strict_types=1);

namespace ML\IDEA\Classifiers;

use ML\IDEA\Contracts\PersistableModelInterface;
use ML\IDEA\Contracts\SerializableModelInterface;
use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Exceptions\ModelNotTrainedException;
use ML\IDEA\Math\Distance;
use ML\IDEA\Model\ModelSerializer;
use ML\IDEA\Support\Assert;

final class KNearestNeighbors extends AbstractClassifier implements PersistableModelInterface, SerializableModelInterface
{
    /** @var array<int, array<int, float|int>> */
    private array $samples = [];

    /** @var array<int, int|float|string|bool> */
    private array $labels = [];

    private int $featureCount = 0;
    private bool $trained = false;

    public function __construct(
        private readonly int $k = 3,
        private readonly bool $weighted = true,
    ) {
        Assert::positiveInt($this->k, 'k');
    }

    public function train(array $samples, array $labels): void
    {
        Assert::numericMatrix($samples);
        Assert::matchingSampleLabelCount($samples, $labels);

        if ($this->k > count($samples)) {
            throw new InvalidArgumentException('k cannot be greater than the number of training samples.');
        }

        $this->samples = $samples;
        $this->labels = $labels;
        $this->featureCount = count($samples[0]);
        $this->trained = true;
    }

    public function predict(array $sample): int|float|string|bool
    {
        if (!$this->trained) {
            throw new ModelNotTrainedException('KNearestNeighbors has not been trained yet.');
        }

        Assert::sampleMatchesDimension($sample, $this->featureCount);

        $distances = [];
        foreach ($this->samples as $i => $trainSample) {
            $distances[] = [
                'distance' => Distance::euclidean($sample, $trainSample),
                'label' => $this->labels[$i],
            ];
        }

        usort(
            $distances,
            static fn (array $a, array $b): int => $a['distance'] <=> $b['distance']
        );

        $neighbors = array_slice($distances, 0, $this->k);

        $votes = [];
        $bestDistance = [];
        $labelMap = [];

        foreach ($neighbors as $neighbor) {
            $key = self::labelKey($neighbor['label']);
            $weight = $this->weighted ? (1.0 / ($neighbor['distance'] + 1.0e-12)) : 1.0;

            if (!isset($votes[$key])) {
                $votes[$key] = 0.0;
                $bestDistance[$key] = INF;
                $labelMap[$key] = $neighbor['label'];
            }

            $votes[$key] += $weight;
            if ($neighbor['distance'] < $bestDistance[$key]) {
                $bestDistance[$key] = $neighbor['distance'];
            }
        }

        $keys = array_keys($votes);
        usort(
            $keys,
            static function (string $a, string $b) use ($votes, $bestDistance): int {
                $voteCompare = $votes[$b] <=> $votes[$a];
                if ($voteCompare !== 0) {
                    return $voteCompare;
                }

                $distanceCompare = $bestDistance[$a] <=> $bestDistance[$b];
                if ($distanceCompare !== 0) {
                    return $distanceCompare;
                }

                return $a <=> $b;
            }
        );

        return $labelMap[$keys[0]];
    }

    public function toArray(): array
    {
        return [
            'k' => $this->k,
            'weighted' => $this->weighted,
            'samples' => $this->samples,
            'labels' => $this->labels,
            'featureCount' => $this->featureCount,
            'trained' => $this->trained,
        ];
    }

    public static function fromArray(array $data): static
    {
        $model = new self(
            (int) ($data['k'] ?? 3),
            (bool) ($data['weighted'] ?? true),
        );

        $model->samples = $data['samples'] ?? [];
        $model->labels = $data['labels'] ?? [];
        $model->featureCount = (int) ($data['featureCount'] ?? 0);
        $model->trained = (bool) ($data['trained'] ?? false);

        return $model;
    }

    public function save(string $path): void
    {
        ModelSerializer::save($this, $path);
    }

    public static function load(string $path): static
    {
        $model = ModelSerializer::load($path);
        if (!$model instanceof static) {
            throw new InvalidArgumentException('Serialized model type mismatch for KNearestNeighbors.');
        }

        return $model;
    }

    private static function labelKey(int|float|string|bool $label): string
    {
        return get_debug_type($label) . ':' . json_encode($label, JSON_THROW_ON_ERROR);
    }
}
