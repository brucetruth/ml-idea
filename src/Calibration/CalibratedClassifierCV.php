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
    private float $a = 1.0;
    private float $b = 0.0;

    private int|float|string|bool $negativeClass = 0;
    private int|float|string|bool $positiveClass = 1;

    public function __construct(
        private readonly ProbabilisticClassifierInterface $baseEstimator,
        private readonly int $cv = 5,
        private readonly int $iterations = 300,
        private readonly float $learningRate = 0.1,
    ) {
        if ($this->cv <= 1) {
            throw new InvalidArgumentException('cv must be greater than 1.');
        }
    }

    public function train(array $samples, array $labels): void
    {
        if ($samples === [] || $labels === [] || count($samples) !== count($labels)) {
            throw new InvalidArgumentException('Samples and labels must be non-empty and same length.');
        }

        $classes = array_values(array_unique($labels, SORT_REGULAR));
        if (count($classes) !== 2) {
            throw new InvalidArgumentException('CalibratedClassifierCV currently supports binary classification only.');
        }

        $this->negativeClass = $classes[0];
        $this->positiveClass = $classes[1];

        $folds = StratifiedKFold::split($labels, $this->cv, true, 42);
        $oofScores = array_fill(0, count($samples), 0.5);

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
                $oofScores[$idx] = isset($proba[$this->positiveClass])
                    ? (float) $proba[$this->positiveClass]
                    : (float) array_values($proba)[0];
            }
        }

        $this->fitPlatt($labels, $oofScores);

        $this->baseEstimator->fit($samples, $labels);
        $this->trained = true;
    }

    public function predict(array $sample): int|float|string|bool
    {
        $proba = $this->predictProba($sample);
        return $proba[$this->positiveClass] >= 0.5 ? $this->positiveClass : $this->negativeClass;
    }

    public function predictProba(array $sample): array
    {
        if (!$this->trained) {
            throw new ModelNotTrainedException('CalibratedClassifierCV has not been trained yet.');
        }

        $base = $this->baseEstimator->predictProba($sample);
        $raw = isset($base[$this->positiveClass]) ? (float) $base[$this->positiveClass] : (float) array_values($base)[0];
        $calibrated = self::sigmoid(($this->a * $raw) + $this->b);

        return [
            $this->negativeClass => 1.0 - $calibrated,
            $this->positiveClass => $calibrated,
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
     * @param array<int, int|float|string|bool> $labels
     * @param array<int, float> $scores
     */
    private function fitPlatt(array $labels, array $scores): void
    {
        $a = 1.0;
        $b = 0.0;
        $n = count($labels);

        for ($iter = 0; $iter < $this->iterations; $iter++) {
            $gradA = 0.0;
            $gradB = 0.0;

            foreach ($labels as $i => $label) {
                $y = $label === $this->positiveClass ? 1.0 : 0.0;
                $s = (float) $scores[$i];
                $p = self::sigmoid(($a * $s) + $b);
                $err = $p - $y;
                $gradA += $err * $s;
                $gradB += $err;
            }

            $a -= $this->learningRate * ($gradA / $n);
            $b -= $this->learningRate * ($gradB / $n);
        }

        $this->a = $a;
        $this->b = $b;
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
