<?php

declare(strict_types=1);

namespace ML\IDEA\Metrics;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Math\Distance;

final class ClusteringMetrics
{
    /**
     * Mean silhouette coefficient for cluster assignments (-1 to 1; higher is better).
     *
     * @param array<int, array<int, float|int>> $samples
     * @param array<int, int> $labels cluster ids per sample
     */
    public static function silhouetteScore(array $samples, array $labels): float
    {
        if ($samples === [] || $labels === [] || count($samples) !== count($labels)) {
            throw new InvalidArgumentException('samples and labels must be non-empty and equal length.');
        }

        $n = count($samples);
        $unique = array_values(array_unique($labels, SORT_REGULAR));
        if (count($unique) <= 1 || count($unique) >= $n) {
            throw new InvalidArgumentException('silhouette requires at least 2 clusters and fewer clusters than samples.');
        }

        $byCluster = [];
        foreach ($labels as $i => $cluster) {
            $byCluster[$cluster][] = $i;
        }

        $scores = [];
        for ($i = 0; $i < $n; $i++) {
            $cluster = $labels[$i];
            $clusterMembers = $byCluster[$cluster];
            if (count($clusterMembers) <= 1) {
                $scores[] = 0.0;
                continue;
            }

            $intraSum = 0.0;
            $intraCount = 0;
            foreach ($clusterMembers as $j) {
                if ($j === $i) {
                    continue;
                }
                $intraSum += Distance::euclidean($samples[$i], $samples[$j]);
                $intraCount++;
            }
            $a = $intraCount > 0 ? $intraSum / $intraCount : 0.0;

            $b = INF;
            foreach ($unique as $otherCluster) {
                if ($otherCluster === $cluster) {
                    continue;
                }
                $otherMembers = $byCluster[$otherCluster];
                $interSum = 0.0;
                foreach ($otherMembers as $j) {
                    $interSum += Distance::euclidean($samples[$i], $samples[$j]);
                }
                $meanInter = $interSum / count($otherMembers);
                if ($meanInter < $b) {
                    $b = $meanInter;
                }
            }

            $denom = max($a, $b);
            $scores[] = $denom > 0.0 ? ($b - $a) / $denom : 0.0;
        }

        return array_sum($scores) / count($scores);
    }
}
