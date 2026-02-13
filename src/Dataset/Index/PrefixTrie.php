<?php

declare(strict_types=1);

namespace ML\IDEA\Dataset\Index;

final class PrefixTrie
{
    /** @var array{children:array<string,mixed>, terminal:bool} */
    private array $root = ['children' => [], 'terminal' => false];

    public function insert(string $term): void
    {
        $chars = $this->chars(mb_strtolower(trim($term)));
        if ($chars === []) {
            return;
        }

        $node =& $this->root;
        foreach ($chars as $char) {
            if (!isset($node['children'][$char])) {
                $node['children'][$char] = ['children' => [], 'terminal' => false];
            }
            $node =& $node['children'][$char];
        }
        $node['terminal'] = true;
    }

    public function contains(string $term): bool
    {
        $chars = $this->chars(mb_strtolower(trim($term)));
        if ($chars === []) {
            return false;
        }

        $node = $this->root;
        foreach ($chars as $char) {
            if (!isset($node['children'][$char])) {
                return false;
            }
            $node = $node['children'][$char];
        }

        return (bool) ($node['terminal'] ?? false);
    }

    /** @return array<int, string> */
    public function suggest(string $prefix, int $limit = 20): array
    {
        $prefix = mb_strtolower(trim($prefix));
        $chars = $this->chars($prefix);
        $node = $this->root;
        foreach ($chars as $char) {
            if (!isset($node['children'][$char])) {
                return [];
            }
            $node = $node['children'][$char];
        }

        $out = [];
        $this->dfs($node, $prefix, $out, $limit);
        return $out;
    }

    /** @param array<int, string> $out */
    private function dfs(array $node, string $prefix, array &$out, int $limit): void
    {
        if (count($out) >= $limit) {
            return;
        }
        if (($node['terminal'] ?? false) === true) {
            $out[] = $prefix;
        }
        foreach (($node['children'] ?? []) as $char => $child) {
            $this->dfs($child, $prefix . $char, $out, $limit);
            if (count($out) >= $limit) {
                return;
            }
        }
    }

    /** @return array<int, string> */
    private function chars(string $text): array
    {
        return preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
