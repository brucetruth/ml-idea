<?php

declare(strict_types=1);

namespace ML\IDEA\Dataset\Index;

final class AhoCorasickAutomaton
{
    /** @var array<int, array{next:array<string,int>, fail:int, out:array<int, array{term:string,label:string,length:int}>}> */
    private array $nodes = [];

    private function __construct()
    {
        $this->nodes[] = ['next' => [], 'fail' => 0, 'out' => []];
    }

    /** @param array<string, string> $patterns */
    public static function fromMap(array $patterns): self
    {
        $ac = new self();
        foreach ($patterns as $term => $label) {
            $ac->insert($term, $label);
        }
        $ac->buildFailures();
        return $ac;
    }

    /** @return array<int, array{term:string,label:string,start:int,end:int}> */
    public function find(string $text): array
    {
        $chars = preg_split('//u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $state = 0;
        $matches = [];

        foreach ($chars as $i => $char) {
            while ($state !== 0 && !isset($this->nodes[$state]['next'][$char])) {
                $state = $this->nodes[$state]['fail'];
            }
            if (isset($this->nodes[$state]['next'][$char])) {
                $state = $this->nodes[$state]['next'][$char];
            }

            foreach ($this->nodes[$state]['out'] as $out) {
                $start = $i - $out['length'] + 1;
                $matches[] = [
                    'term' => $out['term'],
                    'label' => $out['label'],
                    'start' => $start,
                    'end' => $i,
                ];
            }
        }

        return $matches;
    }

    private function insert(string $term, string $label): void
    {
        $term = mb_strtolower(trim($term));
        if ($term === '') {
            return;
        }

        $chars = preg_split('//u', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $state = 0;
        foreach ($chars as $char) {
            if (!isset($this->nodes[$state]['next'][$char])) {
                $this->nodes[] = ['next' => [], 'fail' => 0, 'out' => []];
                $this->nodes[$state]['next'][$char] = count($this->nodes) - 1;
            }
            $state = $this->nodes[$state]['next'][$char];
        }

        $this->nodes[$state]['out'][] = [
            'term' => $term,
            'label' => $label,
            'length' => count($chars),
        ];
    }

    private function buildFailures(): void
    {
        $queue = [];
        foreach ($this->nodes[0]['next'] as $next) {
            $queue[] = $next;
            $this->nodes[$next]['fail'] = 0;
        }

        while ($queue !== []) {
            $r = array_shift($queue);
            if (!is_int($r)) {
                continue;
            }

            foreach ($this->nodes[$r]['next'] as $char => $s) {
                $queue[] = $s;
                $state = $this->nodes[$r]['fail'];
                while ($state !== 0 && !isset($this->nodes[$state]['next'][$char])) {
                    $state = $this->nodes[$state]['fail'];
                }

                $fail = $this->nodes[$state]['next'][$char] ?? 0;
                $this->nodes[$s]['fail'] = $fail;
                $this->nodes[$s]['out'] = array_merge($this->nodes[$s]['out'], $this->nodes[$fail]['out']);
            }
        }
    }
}
