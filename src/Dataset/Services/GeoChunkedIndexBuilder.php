<?php

declare(strict_types=1);

namespace ML\IDEA\Dataset\Services;

use ML\IDEA\Dataset\Registry\DatasetCache;
use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\NLP\Normalize\UnicodeNormalizer;

final class GeoChunkedIndexBuilder
{
    public function __construct(
        private readonly ?string $geoBasePath = null,
        private readonly ?DatasetCache $cache = null,
    ) {
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    public function cityNameIndex(int $maxCities = 30000): array
    {
        $cache = $this->cache ?? new DatasetCache();
        $key = 'geo_city_name_idx_v1_' . $maxCities;
        $cached = $cache->get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $path = $this->geoPath('cities.json');
        $index = [];
        $count = 0;

        foreach ($this->streamObjects($path) as $obj) {
            if (!is_array($obj)) {
                continue;
            }
            $name = $this->norm((string) ($obj['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $index[$name] ??= [];
            $index[$name][] = [
                'name' => (string) ($obj['name'] ?? ''),
                'country_code' => (string) ($obj['country_code'] ?? ''),
                'state_code' => (string) ($obj['state_code'] ?? ''),
                'latitude' => (float) ($obj['latitude'] ?? 0.0),
                'longitude' => (float) ($obj['longitude'] ?? 0.0),
            ];

            $count++;
            if ($count >= $maxCities) {
                break;
            }
        }

        $cache->set($key, $index);
        return $index;
    }

    /** @return array<string, string> */
    public function geoGazetteerMap(int $maxCities = 20000): array
    {
        $cache = $this->cache ?? new DatasetCache();
        $key = 'geo_gazetteer_chunked_v1_' . $maxCities;
        $cached = $cache->get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $map = [];

        foreach ($this->streamObjects($this->geoPath('countries+states.json')) as $country) {
            if (!is_array($country)) {
                continue;
            }
            $name = $this->norm((string) ($country['name'] ?? ''));
            if ($name !== '') {
                $map[$name] = 'COUNTRY';
            }
            foreach (($country['states'] ?? []) as $state) {
                if (!is_array($state)) {
                    continue;
                }
                $sn = $this->norm((string) ($state['name'] ?? ''));
                if ($sn !== '') {
                    $map[$sn] = 'STATE';
                }
            }
        }

        $count = 0;
        foreach ($this->streamObjects($this->geoPath('cities.json')) as $city) {
            if (!is_array($city)) {
                continue;
            }
            $cn = $this->norm((string) ($city['name'] ?? ''));
            if ($cn !== '' && !isset($map[$cn])) {
                $map[$cn] = 'CITY';
            }
            $count++;
            if ($count >= $maxCities) {
                break;
            }
        }

        $cache->set($key, $map);
        return $map;
    }

    /** @return array<string, string> */
    public function countryPhoneIndex(): array
    {
        $cache = $this->cache ?? new DatasetCache();
        $key = 'geo_country_phone_idx_v1';
        $cached = $cache->get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $path = $this->geoPath('countries+states.json');
        $map = [];
        foreach ($this->streamObjects($path) as $country) {
            if (!is_array($country)) {
                continue;
            }
            $phone = trim((string) ($country['phone_code'] ?? ''));
            $iso2 = strtoupper(trim((string) ($country['iso2'] ?? '')));
            if ($phone !== '' && $iso2 !== '') {
                $map[$phone] = $iso2;
            }
        }

        $cache->set($key, $map);
        return $map;
    }

    /** @return \Generator<int, array<string, mixed>> */
    public function streamObjects(string $path): \Generator
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException("Dataset file not found: {$path}");
        }

        $h = fopen($path, 'rb');
        if ($h === false) {
            throw new InvalidArgumentException("Unable to open dataset file: {$path}");
        }

        $depth = 0;
        $inString = false;
        $escape = false;
        $buf = '';

        while (($ch = fgetc($h)) !== false) {
            if ($inString) {
                $buf .= $ch;
                if ($escape) {
                    $escape = false;
                    continue;
                }
                if ($ch === '\\') {
                    $escape = true;
                } elseif ($ch === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($ch === '"') {
                $inString = true;
                if ($depth > 0) {
                    $buf .= $ch;
                }
                continue;
            }

            if ($ch === '{') {
                $depth++;
                $buf .= $ch;
                continue;
            }

            if ($ch === '}') {
                $buf .= $ch;
                $depth--;
                if ($depth === 0) {
                    $decoded = json_decode($buf, true);
                    if (is_array($decoded)) {
                        yield $decoded;
                    }
                    $buf = '';
                }
                continue;
            }

            if ($depth > 0) {
                $buf .= $ch;
            }
        }

        fclose($h);
    }

    private function geoPath(string $file): string
    {
        $base = $this->geoBasePath ?? dirname(__DIR__, 2) . '/Dataset/geo';
        return rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;
    }

    private function norm(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', mb_strtolower(UnicodeNormalizer::stripAccents($value))));
    }
}
