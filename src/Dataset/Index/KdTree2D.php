<?php

declare(strict_types=1);

namespace ML\IDEA\Dataset\Index;

final class KdTree2D
{
    /** @var array<string, mixed>|null */
    private ?array $root;
    /** @var array<int, array{x:float,y:float,payload:array<string,mixed>}> */
    private array $points;

    /**
     * @param array<int, array{x:float,y:float,payload:array<string,mixed>}> $points
     */
    public function __construct(array $points)
    {
        $this->points = $points;
        $this->root = $this->build($points, 0);
    }

    /** @return array{payload:array<string,mixed>, distance:float}|null */
    public function nearest(float $x, float $y): ?array
    {
        if ($this->root === null) {
            return null;
        }

        $best = ['distance' => INF, 'payload' => []];
        $this->search($this->root, $x, $y, 0, $best);
        if ($best['distance'] === INF) {
            return null;
        }

        return [
            'payload' => $best['payload'],
            'distance' => sqrt($best['distance']),
        ];
    }

    /** @return array<int, array{payload:array<string,mixed>, distance:float}> */
    public function kNearest(float $x, float $y, int $k = 5): array
    {
        $k = max(1, $k);
        if ($this->points === []) {
            return [];
        }

        $hits = [];
        foreach ($this->points as $point) {
            $dx = $point['x'] - $x;
            $dy = $point['y'] - $y;
            $hits[] = [
                'payload' => $point['payload'],
                'distance' => sqrt(($dx * $dx) + ($dy * $dy)),
            ];
        }

        usort($hits, static fn (array $a, array $b): int => $a['distance'] <=> $b['distance']);
        return array_slice($hits, 0, $k);
    }

    /**
     * @param array<int, array{x:float,y:float,payload:array<string,mixed>}> $points
     * @return array<string, mixed>|null
     */
    private function build(array $points, int $depth): ?array
    {
        if ($points === []) {
            return null;
        }

        $axis = $depth % 2;
        usort($points, static function (array $a, array $b) use ($axis): int {
            $ka = $axis === 0 ? $a['x'] : $a['y'];
            $kb = $axis === 0 ? $b['x'] : $b['y'];
            return $ka <=> $kb;
        });

        $mid = intdiv(count($points), 2);
        return [
            'point' => $points[$mid],
            'left' => $this->build(array_slice($points, 0, $mid), $depth + 1),
            'right' => $this->build(array_slice($points, $mid + 1), $depth + 1),
        ];
    }

    /** @param array{distance:float,payload:array<string,mixed>} $best */
    private function search(array $node, float $x, float $y, int $depth, array &$best): void
    {
        $point = $node['point'];
        $dx = $point['x'] - $x;
        $dy = $point['y'] - $y;
        $dist2 = $dx * $dx + $dy * $dy;
        if ($dist2 < $best['distance']) {
            $best['distance'] = $dist2;
            $best['payload'] = $point['payload'];
        }

        $axis = $depth % 2;
        $delta = $axis === 0 ? ($x - $point['x']) : ($y - $point['y']);
        $first = $delta < 0 ? 'left' : 'right';
        $second = $delta < 0 ? 'right' : 'left';

        if (is_array($node[$first] ?? null)) {
            $this->search($node[$first], $x, $y, $depth + 1, $best);
        }
        if (($delta * $delta) < $best['distance'] && is_array($node[$second] ?? null)) {
            $this->search($node[$second], $x, $y, $depth + 1, $best);
        }
    }
}
