<?php

declare(strict_types=1);

namespace ML\IDEA\Vision\Analyzers;

use ML\IDEA\Exceptions\InvalidArgumentException;

final class SkinToneHeuristicAnalyzer
{
    public function __construct(
        private readonly float $mediumThreshold = 0.30,
        private readonly float $highThreshold = 0.55,
    ) {
    }

    /**
     * @param array<int, array{0:float|int,1:float|int,2:float|int}> $rgbSamples
     * @return array{skin_ratio: float, non_skin_ratio: float, risk_level: string, total_samples: int}
     */
    public function analyze(array $rgbSamples): array
    {
        if ($rgbSamples === []) {
            throw new InvalidArgumentException('rgbSamples cannot be empty.');
        }

        $skin = 0;
        $total = count($rgbSamples);

        foreach ($rgbSamples as $s) {
            if ($this->isSkin((float) $s[0], (float) $s[1], (float) $s[2])) {
                $skin++;
            }
        }

        $ratio = $skin / $total;
        $risk = 'low';
        if ($ratio >= $this->highThreshold) {
            $risk = 'high';
        } elseif ($ratio >= $this->mediumThreshold) {
            $risk = 'medium';
        }

        return [
            'skin_ratio' => $ratio,
            'non_skin_ratio' => 1.0 - $ratio,
            'risk_level' => $risk,
            'total_samples' => $total,
        ];
    }

    private function isSkin(float $r, float $g, float $b): bool
    {
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);

        $rgbRule = $r > 95.0
            && $g > 40.0
            && $b > 20.0
            && ($max - $min) > 15.0
            && abs($r - $g) > 15.0
            && $r > $g
            && $r > $b;

        $cb = -0.168736 * $r - 0.331264 * $g + 0.5 * $b + 128.0;
        $cr = 0.5 * $r - 0.418688 * $g - 0.081312 * $b + 128.0;
        $ycbcrRule = $cb >= 77.0 && $cb <= 127.0 && $cr >= 133.0 && $cr <= 173.0;

        return $rgbRule || $ycbcrRule;
    }
}
