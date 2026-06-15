<?php

declare(strict_types=1);

namespace ML\IDEA\Calibration;

/** Monotonic piecewise-linear calibrator (pool adjacent violators lite). */
final class IsotonicRegression
{
    /**
     * @param array<int, float> $scores
     * @param array<int, float> $targets binary 0/1
     * @return array{x: array<int, float>, y: array<int, float>}
     */
    public static function fit(array $scores, array $targets): array
    {
        $pairs = [];
        foreach ($scores as $i => $score) {
            $pairs[] = ['x' => (float) $score, 'y' => (float) $targets[$i]];
        }

        usort($pairs, static fn (array $a, array $b): int => $a['x'] <=> $b['x']);

        $x = array_column($pairs, 'x');
        $y = array_column($pairs, 'y');

        // Simple bin averaging for stability on small OOF sets
        $bucketX = [];
        $bucketY = [];
        $bucketN = [];
        foreach ($x as $i => $value) {
            $bucketX[] = $value;
            $bucketY[] = $y[$i];
            $bucketN[] = 1;
        }

        $changed = true;
        while ($changed && count($bucketY) > 1) {
            $changed = false;
            for ($i = 0; $i < count($bucketY) - 1; $i++) {
                if ($bucketY[$i] > $bucketY[$i + 1]) {
                    $n = $bucketN[$i] + $bucketN[$i + 1];
                    $avg = (($bucketY[$i] * $bucketN[$i]) + ($bucketY[$i + 1] * $bucketN[$i + 1])) / $n;
                    $bucketY[$i] = $avg;
                    $bucketY[$i + 1] = $avg;
                    $changed = true;
                }
            }
        }

        return ['x' => $bucketX, 'y' => $bucketY];
    }

    /** @param array{x: array<int, float>, y: array<int, float>} $model */
    public static function predict(array $model, float $score): float
    {
        $xs = $model['x'];
        $ys = $model['y'];
        if ($xs === []) {
            return 0.5;
        }

        if ($score <= $xs[0]) {
            return max(0.0, min(1.0, $ys[0]));
        }

        $last = count($xs) - 1;
        if ($score >= $xs[$last]) {
            return max(0.0, min(1.0, $ys[$last]));
        }

        for ($i = 0; $i < $last; $i++) {
            if ($score >= $xs[$i] && $score <= $xs[$i + 1]) {
                $dx = $xs[$i + 1] - $xs[$i];
                if ($dx <= 0.0) {
                    return max(0.0, min(1.0, $ys[$i]));
                }
                $t = ($score - $xs[$i]) / $dx;

                return max(0.0, min(1.0, $ys[$i] + ($t * ($ys[$i + 1] - $ys[$i]))));
            }
        }

        return 0.5;
    }
}
