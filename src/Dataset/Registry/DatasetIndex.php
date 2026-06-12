<?php

declare(strict_types=1);

namespace ML\IDEA\Dataset\Registry;

use ML\IDEA\Dataset\Index\AhoCorasickAutomaton;
use ML\IDEA\Dataset\Index\KdTree2D;
use ML\IDEA\Dataset\Index\PrefixTrie;
use ML\IDEA\Dataset\Services\GeoChunkedIndexBuilder;
use ML\IDEA\Dataset\Services\GeoDatasetService;

final class DatasetIndex
{
    private DatasetCache $cache;

    public function __construct(
        private readonly ?string $datasetBasePath = null,
        ?DatasetCache $cache = null,
    ) {
        $this->cache = $cache ?? new DatasetCache();
    }

    /** @return array<string, string> */
    public function geoGazetteerMap(int $maxCities = 10000): array
    {
        $key = 'geo_gazetteer_map_' . $maxCities;
        if ($this->cache->has($key)) {
            $cached = $this->cache->get($key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $map = (new GeoChunkedIndexBuilder(
            $this->geoBasePath(),
            $this->cache,
        ))->geoGazetteerMap($maxCities);

        $this->cache->set($key, $map);
        return $map;
    }

    public function geoGazetteerAutomaton(int $maxCities = 10000): AhoCorasickAutomaton
    {
        return AhoCorasickAutomaton::fromMap($this->geoGazetteerMap($maxCities));
    }

    public function geoTrie(int $maxCities = 10000): PrefixTrie
    {
        $trie = new PrefixTrie();
        foreach (array_keys($this->geoGazetteerMap($maxCities)) as $term) {
            $trie->insert($term);
        }
        return $trie;
    }

    public function geoKdTree(int $maxCities = 20000): KdTree2D
    {
        $key = 'geo_kdtree_payloads_' . $maxCities;
        $points = $this->cache->get($key);
        if (!is_array($points)) {
            $geo = new GeoDatasetService($this->datasetBasePath === null ? null : $this->datasetBasePath . '/geo');
            $points = [];
            $count = 0;
            foreach ($geo->streamCities() as $city) {
                if (!is_array($city)) {
                    continue;
                }
                $points[] = [
                    'x' => (float) ($city['latitude'] ?? 0.0),
                    'y' => (float) ($city['longitude'] ?? 0.0),
                    'payload' => $city,
                ];
                $count++;
                if ($count >= $maxCities) {
                    break;
                }
            }
            $this->cache->set($key, $points);
        }

        /** @var array<int, array{x:float,y:float,payload:array<string,mixed>}> $points */
        return new KdTree2D($points);
    }

    private function geoBasePath(): ?string
    {
        return $this->datasetBasePath === null ? null : $this->datasetBasePath . '/geo';
    }
}
