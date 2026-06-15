<?php

declare(strict_types=1);

namespace ML\IDEA\Calibration;

use ML\IDEA\Classifiers\AbstractClassifier;
use ML\IDEA\Contracts\ProbabilisticClassifierInterface;
use ML\IDEA\Data\StratifiedKFold;
use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Exceptions\ModelNotTrainedException;

final class CalibratedClassifierCV extends AbstractClassifier implements ProbabilisticClassifierInterface
{
    private bool $trained = false;

    /** @var array<int, int|float|string|bool> */
    private array $classes = [];

    /** @var array<int|float|string|bool, array{a: float, b: float}> */
    private array $platt = [];

    /** @var array<int|float|string|bool, array{x: array<int, float>, y: array<int, float>}> */
    private array $isotonicPerClass = [];

    public function __construct(
        private readonly ProbabilisticClassifierInterface $baseEstimator,
        private readonly int|string $cv = 5,
        private readonly string $method = 'platt',
        private readonly int $iterations = 300,
        private readonly float $learningRate = 0.1,
    ) {
        if ($this->cv !== 'prefit' && (is_int($this->cv) && $this->cv <= 1)) {
            throw new InvalidArgumentException('cv must be greater than 1, or "prefit".');
        }
        if (!in_array($this->method, ['platt', 'isotonic'], true)) {
            throw new InvalidArgumentException('method must be platt or isotonic.');
        }
    }

    public function train(array $samples, array $labels): void
    {
        if ($samples === [] || $labels === [] || count($samples) !== count($labels)) {
            throw new InvalidArgumentException('Samples and labels must be non-empty and same length.');
        }

        $this->classes = array_values(array_unique($labels, SORT_REGULAR));
        if (count($this->classes) < 2) {
            throw new InvalidArgumentException('CalibratedClassifierCV requires at least 2 classes.');
        }

        if ($this->cv === 'prefit') {
            $this->calibratePrefit($samples, $labels);
            $this->trained = true;

            return;
        }

        $cv = min((int) $this->cv, count($samples), StratifiedKFold::maxSplits($labels));
        if ($cv <= 1) {
            throw new InvalidArgumentException('Not enough samples for cross-validation.');
        }

        $folds = StratifiedKFold::split($labels, $cv, true, 42);
        $n = count($samples);

        /** @var array<int|float|string|bool, array<int, float>> $oofScores per class per sample */
        $oofScores = [];
        foreach ($this->classes as $class) {
            $oofScores[$class] = array_fill(0, $n, 0.0);
        }

        foreach ($folds as $fold) {
            $xTrain = $yTrain = $xTest = [];
            foreach ($fold['train'] as $idx) {
                $xTrain[] = $samples[$idx];
                $yTrain[] = $labels[$idx];
            }
            foreach ($fold['test'] as $idx) {
                $xTest[] = $samples[$idx];
            }

            $model = $this->baseEstimator->cloneWithParams([]);
            $model->fit($xTrain, $yTrain);

            foreach ($fold['test'] as $j => $idx) {
                $proba = $model->predictProba($xTest[$j]);
                foreach ($this->classes as $class) {
                    $oofScores[$class][$idx] = (float) ($proba[$class] ?? 0.0);
                }
            }
        }

        $this->platt = [];
        $this->isotonicPerClass = [];

        foreach ($this->classes as $class) {
            $targets = array_map(
                static fn ($label): float => $label === $class ? 1.0 : 0.0,
                $labels,
            );

            if ($this->method === 'isotonic') {
                $this->isotonicPerClass[$class] = IsotonicRegression::fit($oofScores[$class], $targets);
            } else {
                $this->platt[$class] = self::fitPlatt($targets, $oofScores[$class], $this->iterations, $this->learningRate);
            }
        }

        $this->baseEstimator->fit($samples, $labels);
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
            throw new ModelNotTrainedException('CalibratedClassifierCV has not been trained yet.');
        }

        $base = $this->baseEstimator->predictProba($sample);
        $calibrated = [];

        foreach ($this->classes as $class) {
            $raw = (float) ($base[$class] ?? 0.0);
            if ($this->method === 'isotonic') {
                $model = $this->isotonicPerClass[$class] ?? null;
                $calibrated[$class] = $model !== null ? IsotonicRegression::predict($model, $raw) : $raw;
            } else {
                $params = $this->platt[$class] ?? ['a' => 1.0, 'b' => 0.0];
                $calibrated[$class] = self::sigmoid(($params['a'] * $raw) + $params['b']);
            }
        }

        return self::normalize($calibrated);
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
     * @param array<int, int|float|string|bool> $labels
     * @param array<int, float> $scores
     * @return array{a: float, b: float}
     */
    private static function fitPlatt(array $labels, array $scores, int $iterations, float $learningRate): array
    {
        $a = 1.0;
        $b = 0.0;
        $n = count($labels);

        for ($iter = 0; $iter < $iterations; $iter++) {
            $gradA = 0.0;
            $gradB = 0.0;

            foreach ($labels as $i => $target) {
                $y = (float) $target;
                $s = (float) $scores[$i];
                $p = self::sigmoid(($a * $s) + $b);
                $err = $p - $y;
                $gradA += $err * $s;
                $gradB += $err;
            }

            $a -= $learningRate * ($gradA / $n);
            $b -= $learningRate * ($gradB / $n);
        }

        return ['a' => $a, 'b' => $b];
    }

    /** @param array<int|float|string|bool, float> $proba */
    private static function normalize(array $proba): array
    {
        $sum = array_sum($proba);
        if ($sum <= 0.0) {
            $uniform = 1.0 / count($proba);

            return array_map(static fn (): float => $uniform, $proba);
        }

        $normalized = [];
        foreach ($proba as $class => $value) {
            $normalized[$class] = max(0.0, (float) $value) / $sum;
        }

        return $normalized;
    }

    /**
     * Calibrate an already-fitted base estimator on a holdout set (sklearn cv='prefit').
     *
     * @param array<int, array<int, float|int>> $samples
     * @param array<int, int|float|string|bool> $labels
     */
    private function calibratePrefit(array $samples, array $labels): void
    {
        $n = count($samples);
        $oofScores = [];
        foreach ($this->classes as $class) {
            $oofScores[$class] = array_fill(0, $n, 0.0);
        }

        foreach ($samples as $i => $sample) {
            try {
                $proba = $this->baseEstimator->predictProba($sample);
            } catch (ModelNotTrainedException) {
                throw new InvalidArgumentException('prefit mode requires an already-trained base estimator.');
            }

            foreach ($this->classes as $class) {
                $oofScores[$class][$i] = (float) ($proba[$class] ?? 0.0);
            }
        }

        $this->platt = [];
        $this->isotonicPerClass = [];

        foreach ($this->classes as $class) {
            $targets = array_map(
                static fn ($label): float => $label === $class ? 1.0 : 0.0,
                $labels,
            );

            if ($this->method === 'isotonic') {
                $this->isotonicPerClass[$class] = IsotonicRegression::fit($oofScores[$class], $targets);
            } else {
                $this->platt[$class] = self::fitPlatt($targets, $oofScores[$class], $this->iterations, $this->learningRate);
            }
        }
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
