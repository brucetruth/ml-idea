<?php

declare(strict_types=1);

namespace ML\IDEA\Classifiers;

use ML\IDEA\Contracts\PersistableModelInterface;
use ML\IDEA\Contracts\SerializableModelInterface;
use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Exceptions\ModelNotTrainedException;
use ML\IDEA\Model\ModelSerializer;
use ML\IDEA\Support\Assert;

final class GaussianNaiveBayes extends AbstractClassifier implements PersistableModelInterface, SerializableModelInterface
{
    /** @var array<string, int|float|string|bool> */
    private array $classMap = [];

    /** @var array<string, float> */
    private array $classPriors = [];

    /** @var array<string, array<int, float>> */
    private array $means = [];

    /** @var array<string, array<int, float>> */
    private array $variances = [];

    private int $featureCount = 0;
    private bool $trained = false;

    public function __construct(private readonly float $varianceSmoothing = 1.0e-9)
    {
    }

    public function train(array $samples, array $labels): void
    {
        Assert::numericMatrix($samples);
        Assert::matchingSampleLabelCount($samples, $labels);

        $this->featureCount = count($samples[0]);
        $sampleCount = count($samples);

        $grouped = [];
        foreach ($samples as $i => $sample) {
            $key = self::labelKey($labels[$i]);
            $this->classMap[$key] = $labels[$i];
            $grouped[$key][] = $sample;
        }

        foreach ($grouped as $key => $rows) {
            $count = count($rows);
            $this->classPriors[$key] = $count / $sampleCount;

            $means = array_fill(0, $this->featureCount, 0.0);
            foreach ($rows as $row) {
                foreach ($row as $j => $value) {
                    $means[$j] += (float) $value;
                }
            }
            foreach ($means as $j => $sum) {
                $means[$j] = $sum / $count;
            }

            $variances = array_fill(0, $this->featureCount, 0.0);
            foreach ($rows as $row) {
                foreach ($row as $j => $value) {
                    $delta = (float) $value - $means[$j];
                    $variances[$j] += $delta * $delta;
                }
            }
            foreach ($variances as $j => $sum) {
                $variances[$j] = max($sum / max(1, $count - 1), $this->varianceSmoothing);
            }

            $this->means[$key] = $means;
            $this->variances[$key] = $variances;
        }

        $this->trained = true;
    }

    public function predict(array $sample): int|float|string|bool
    {
        if (!$this->trained) {
            throw new ModelNotTrainedException('GaussianNaiveBayes has not been trained yet.');
        }

        Assert::sampleMatchesDimension($sample, $this->featureCount);

        $bestKey = null;
        $bestLogProb = -INF;

        foreach ($this->classPriors as $key => $prior) {
            $logProb = log($prior);
            foreach ($sample as $j => $value) {
                $mean = $this->means[$key][$j];
                $variance = $this->variances[$key][$j];
                $x = (float) $value;

                $logProb += -0.5 * log(2 * M_PI * $variance);
                $logProb += -((($x - $mean) ** 2) / (2 * $variance));
            }

            if ($logProb > $bestLogProb) {
                $bestLogProb = $logProb;
                $bestKey = $key;
            }
        }

        /** @var string $bestKey */
        return $this->classMap[$bestKey];
    }

    public function toArray(): array
    {
        return [
            'classMap' => $this->classMap,
            'classPriors' => $this->classPriors,
            'means' => $this->means,
            'variances' => $this->variances,
            'featureCount' => $this->featureCount,
            'trained' => $this->trained,
            'varianceSmoothing' => $this->varianceSmoothing,
        ];
    }

    public static function fromArray(array $data): static
    {
        $model = new self((float) ($data['varianceSmoothing'] ?? 1.0e-9));
        $model->classMap = $data['classMap'] ?? [];
        $model->classPriors = $data['classPriors'] ?? [];
        $model->means = $data['means'] ?? [];
        $model->variances = $data['variances'] ?? [];
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
            throw new InvalidArgumentException('Serialized model type mismatch for GaussianNaiveBayes.');
        }

        return $model;
    }

    private static function labelKey(int|float|string|bool $label): string
    {
        return get_debug_type($label) . ':' . json_encode($label, JSON_THROW_ON_ERROR);
    }
}
