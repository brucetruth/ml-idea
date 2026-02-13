<?php

declare(strict_types=1);

namespace ML\IDEA\Vision\Analyzers;

use ML\IDEA\Clustering\MiniBatchKMeans;
use ML\IDEA\Exceptions\InvalidArgumentException;

final class ColorPaletteAnalyzer
{
    public function __construct(
        private readonly int $k = 5,
        private readonly int $maxIterations = 80,
        private readonly int $batchSize = 128,
        private readonly ?int $seed = 42,
    ) {
    }

    /**
     * @param array<int, array{0:float|int,1:float|int,2:float|int}> $rgbSamples
     * @return array{palette: array<int, array{cluster:int, rgb: array{0:int,1:int,2:int}, hex:string, percentage:float}>, total_samples:int}
     */
    public function analyze(array $rgbSamples): array
    {
        if ($rgbSamples === []) {
            throw new InvalidArgumentException('rgbSamples cannot be empty.');
        }

        $samples = [];
        foreach ($rgbSamples as $s) {
            $samples[] = [
                self::clamp((float) $s[0]),
                self::clamp((float) $s[1]),
                self::clamp((float) $s[2]),
            ];
        }

        $n = count($samples);
        if ($n === 1) {
            $rgb = [
                (int) round($samples[0][0]),
                (int) round($samples[0][1]),
                (int) round($samples[0][2]),
            ];

            return [
                'palette' => [[
                    'cluster' => 0,
                    'rgb' => $rgb,
                    'hex' => self::toHex($rgb),
                    'percentage' => 1.0,
                ]],
                'total_samples' => 1,
            ];
        }

        $effectiveK = max(2, min($this->k, $n));
        $kmeans = new MiniBatchKMeans($effectiveK, $this->maxIterations, min($this->batchSize, $n), $this->seed);
        $kmeans->fit($samples);
        $clusters = $kmeans->predictBatch($samples);

        $counts = [];
        $sums = [];
        foreach ($clusters as $i => $cluster) {
            if (!isset($counts[$cluster])) {
                $counts[$cluster] = 0;
                $sums[$cluster] = [0.0, 0.0, 0.0];
            }

            $counts[$cluster]++;
            $sums[$cluster][0] += $samples[$i][0];
            $sums[$cluster][1] += $samples[$i][1];
            $sums[$cluster][2] += $samples[$i][2];
        }

        $palette = [];
        foreach ($counts as $cluster => $count) {
            $rgb = [
                (int) round($sums[$cluster][0] / $count),
                (int) round($sums[$cluster][1] / $count),
                (int) round($sums[$cluster][2] / $count),
            ];

            $palette[] = [
                'cluster' => (int) $cluster,
                'rgb' => $rgb,
                'hex' => self::toHex($rgb),
                'percentage' => $count / $n,
            ];
        }

        usort($palette, static fn (array $a, array $b): int => $b['percentage'] <=> $a['percentage']);
        return ['palette' => $palette, 'total_samples' => $n];
    }

    /** @param array{0:int,1:int,2:int} $rgb */
    private static function toHex(array $rgb): string
    {
        return sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
    }

    private static function clamp(float $v): float
    {
        return max(0.0, min(255.0, $v));
    }
}
