<?php

declare(strict_types=1);

namespace ML\IDEA\Geo;

use ML\IDEA\Dataset\Index\KdTree2D;
use ML\IDEA\Dataset\Registry\DatasetIndex;
use ML\IDEA\Dataset\Services\GeoDatasetService;
use ML\IDEA\NLP\Normalize\UnicodeNormalizer;

final class GeoService
{
    private ?KdTree2D $cityTree = null;
    /** @var array<string, array{lat:float,lon:float,name:string,iso2:string,iso3:string}>|null */
    private ?array $countryCentroids = null;
    /** @var array<string, array{minLat:float,maxLat:float,minLon:float,maxLon:float}>|null */
    private ?array $countryBboxes = null;

    public function __construct(
        private readonly GeoDatasetService $dataset = new GeoDatasetService(),
        private readonly ?DatasetIndex $index = null,
    )
    {
    }

    /** @return array<int, string> */
    public function countries(): array
    {
        $rows = $this->dataset->countriesWithStates();
        $out = [];
        foreach ($rows as $row) {
            $name = (string) ($row['name'] ?? '');
            if ($name !== '') {
                $out[] = $name;
            }
        }

        sort($out);
        return $out;
    }

    /** @return array<string, mixed>|null */
    public function country(string $countryNameOrCode): ?array
    {
        $needle = mb_strtolower(trim($countryNameOrCode));
        foreach ($this->dataset->countriesWithStates() as $row) {
            $name = mb_strtolower((string) ($row['name'] ?? ''));
            $iso2 = mb_strtolower((string) ($row['iso2'] ?? ''));
            $iso3 = mb_strtolower((string) ($row['iso3'] ?? ''));
            if ($needle === $name || $needle === $iso2 || $needle === $iso3) {
                return $row;
            }
        }

        return null;
    }

    /** @return array<int, string> */
    public function statesOf(string $countryNameOrCode): array
    {
        $country = $this->country($countryNameOrCode);
        if ($country === null) {
            return [];
        }

        $states = [];
        foreach (($country['states'] ?? []) as $row) {
            if (is_array($row) && isset($row['name'])) {
                $states[] = (string) $row['name'];
            }
        }

        sort($states);
        return $states;
    }

    /** @return array<int, array<string, mixed>> */
    public function citiesOf(string $countryNameOrCode, ?string $state = null, int $limit = 500): array
    {
        $country = $this->country($countryNameOrCode);
        if ($country === null) {
            return [];
        }

        $countryCode = (string) ($country['iso2'] ?? '');
        $stateNeedle = $state === null ? null : mb_strtolower(trim($state));

        $out = [];
        foreach ($this->dataset->cities() as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ((string) ($row['country_code'] ?? '') !== $countryCode) {
                continue;
            }
            if ($stateNeedle !== null && mb_strtolower((string) ($row['state_code'] ?? '')) !== $stateNeedle
                && mb_strtolower((string) ($row['state_name'] ?? '')) !== $stateNeedle) {
                // keep compatible with current dataset (state_code available)
                continue;
            }
            $out[] = $row;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function nearestCity(float $latitude, float $longitude, ?string $countryCode = null): ?array
    {
        $cities = $this->nearestCities($latitude, $longitude, 1, null, $countryCode);
        return $cities[0] ?? null;
    }

    /** @return array<int, array<string, mixed>> */
    public function nearestCities(
        float $latitude,
        float $longitude,
        int $topK = 5,
        ?float $withinKm = null,
        ?string $countryCode = null,
    ): array {
        $topK = max(1, $topK);
        $countryCode = $countryCode === null ? null : strtoupper(trim($countryCode));

        $hits = [];
        if ($countryCode === null) {
            $tree = $this->cityTree();
            foreach ($tree->kNearest($latitude, $longitude, max($topK * 4, 20)) as $hit) {
                $payload = $hit['payload'];
                $lat = (float) ($payload['latitude'] ?? 0.0);
                $lon = (float) ($payload['longitude'] ?? 0.0);
                $dist = self::haversineKm($latitude, $longitude, $lat, $lon);
                if ($withinKm !== null && $dist > $withinKm) {
                    continue;
                }
                $payload['distance_km'] = $dist;
                $hits[] = $payload;
                if (count($hits) >= $topK) {
                    break;
                }
            }

            if ($hits !== []) {
                return $hits;
            }
        }

        foreach ($this->dataset->cities() as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ($countryCode !== null && strtoupper((string) ($row['country_code'] ?? '')) !== $countryCode) {
                continue;
            }

            $lat = (float) ($row['latitude'] ?? 0.0);
            $lon = (float) ($row['longitude'] ?? 0.0);
            $dist = self::haversineKm($latitude, $longitude, $lat, $lon);
            if ($withinKm !== null && $dist > $withinKm) {
                continue;
            }

            $row['distance_km'] = $dist;
            $hits[] = $row;
        }

        usort($hits, static fn (array $a, array $b): int => ((float) ($a['distance_km'] ?? INF)) <=> ((float) ($b['distance_km'] ?? INF)));
        return array_slice($hits, 0, $topK);
    }

    /** @return array<string, mixed>|null */
    public function countryFromCoordinates(float $latitude, float $longitude): ?array
    {
        $centroids = $this->countryCentroids();
        $bboxes = $this->countryBboxes();

        $candidateCodes = [];
        foreach ($bboxes as $code => $bbox) {
            if ($latitude >= $bbox['minLat'] && $latitude <= $bbox['maxLat']
                && $longitude >= $bbox['minLon'] && $longitude <= $bbox['maxLon']) {
                $candidateCodes[] = $code;
            }
        }
        if ($candidateCodes === []) {
            $candidateCodes = array_keys($centroids);
        }

        $bestCode = null;
        $bestDist = INF;
        foreach ($candidateCodes as $code) {
            $c = $centroids[$code] ?? null;
            if ($c === null) {
                continue;
            }
            $dist = self::haversineKm($latitude, $longitude, $c['lat'], $c['lon']);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $bestCode = $code;
            }
        }
        if ($bestCode === null) {
            return null;
        }

        $country = $this->country($bestCode);
        if ($country === null) {
            return null;
        }

        $country['distance_km'] = $bestDist;
        $country['match_method'] = $candidateCodes === array_keys($centroids) ? 'nearest_centroid' : 'bbox_then_nearest_centroid';
        return $country;
    }

    /** @return array<int, array{type:string,name:string,country_code:string,state_code:string,confidence:float}> */
    public function searchPlace(string $query, int $topK = 5): array
    {
        $topK = max(1, $topK);
        $needle = $this->norm($query);
        if ($needle === '') {
            return [];
        }

        $results = [];

        foreach ($this->dataset->countriesWithStates() as $country) {
            if (!is_array($country)) {
                continue;
            }
            $name = (string) ($country['name'] ?? '');
            $iso2 = (string) ($country['iso2'] ?? '');
            $score = $this->textSimilarity($needle, $this->norm($name));
            $score = max($score, $this->textSimilarity($needle, $this->norm($iso2)));
            if ($score >= 0.55) {
                $results[] = [
                    'type' => 'COUNTRY',
                    'name' => $name,
                    'country_code' => $iso2,
                    'state_code' => '',
                    'confidence' => $score,
                ];
            }

            foreach (($country['states'] ?? []) as $state) {
                if (!is_array($state)) {
                    continue;
                }
                $stateName = (string) ($state['name'] ?? '');
                $stateCode = (string) ($state['state_code'] ?? '');
                $s = $this->textSimilarity($needle, $this->norm($stateName));
                if ($s >= 0.55) {
                    $results[] = [
                        'type' => 'STATE',
                        'name' => $stateName,
                        'country_code' => $iso2,
                        'state_code' => $stateCode,
                        'confidence' => $s,
                    ];
                }
            }
        }

        foreach ($this->dataset->cities() as $city) {
            if (!is_array($city)) {
                continue;
            }
            $name = (string) ($city['name'] ?? '');
            $countryCode = (string) ($city['country_code'] ?? '');
            $stateCode = (string) ($city['state_code'] ?? '');
            $score = $this->textSimilarity($needle, $this->norm($name));
            if ($score >= 0.55) {
                $results[] = [
                    'type' => 'CITY',
                    'name' => $name,
                    'country_code' => $countryCode,
                    'state_code' => $stateCode,
                    'confidence' => $score,
                ];
            }
        }

        usort($results, static fn (array $a, array $b): int => $b['confidence'] <=> $a['confidence']);
        return array_slice($results, 0, $topK);
    }

    /** @return array<string, string> */
    public function geoGazetteer(int $maxCities = 10000): array
    {
        if ($this->index !== null) {
            return $this->index->geoGazetteerMap($maxCities);
        }

        $map = [];
        foreach ($this->dataset->countriesWithStates() as $country) {
            $name = mb_strtolower((string) ($country['name'] ?? ''));
            if ($name !== '') {
                $map[$name] = 'COUNTRY';
            }
            foreach (($country['states'] ?? []) as $state) {
                if (!is_array($state)) {
                    continue;
                }
                $sn = mb_strtolower((string) ($state['name'] ?? ''));
                if ($sn !== '') {
                    $map[$sn] = 'STATE';
                }
            }
        }

        $count = 0;
        foreach ($this->dataset->cities() as $city) {
            if (!is_array($city)) {
                continue;
            }
            $cn = mb_strtolower((string) ($city['name'] ?? ''));
            if ($cn !== '' && !isset($map[$cn])) {
                $map[$cn] = 'CITY';
            }
            $count++;
            if ($count >= $maxCities) {
                break;
            }
        }

        return $map;
    }

    private static function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return 2 * $r * asin(min(1.0, sqrt($a)));
    }

    private function cityTree(): KdTree2D
    {
        if ($this->cityTree !== null) {
            return $this->cityTree;
        }

        if ($this->index !== null) {
            $this->cityTree = $this->index->geoKdTree(20000);
            return $this->cityTree;
        }

        $points = [];
        foreach ($this->dataset->cities() as $city) {
            if (!is_array($city)) {
                continue;
            }
            $points[] = [
                'x' => (float) ($city['latitude'] ?? 0.0),
                'y' => (float) ($city['longitude'] ?? 0.0),
                'payload' => $city,
            ];
        }

        /** @var array<int, array{x:float,y:float,payload:array<string,mixed>}> $points */
        $this->cityTree = new KdTree2D($points);
        return $this->cityTree;
    }

    /** @return array<string, array{lat:float,lon:float,name:string,iso2:string,iso3:string}> */
    private function countryCentroids(): array
    {
        if ($this->countryCentroids !== null) {
            return $this->countryCentroids;
        }

        $centroids = [];
        foreach ($this->dataset->countriesWithStates() as $country) {
            if (!is_array($country)) {
                continue;
            }
            $iso2 = strtoupper((string) ($country['iso2'] ?? ''));
            if ($iso2 === '') {
                continue;
            }

            $name = (string) ($country['name'] ?? '');
            $iso3 = (string) ($country['iso3'] ?? '');

            $lat = null;
            $lon = null;
            foreach ($this->dataset->countries() as $rc) {
                if (!is_array($rc)) {
                    continue;
                }
                $cca2 = strtoupper((string) ($rc['cca2'] ?? ''));
                $common = mb_strtolower((string) (($rc['name']['common'] ?? '') ?: ''));
                if (($cca2 !== '' && $cca2 === $iso2) || ($common !== '' && $common === mb_strtolower($name))) {
                    $latlng = $rc['latlng'] ?? null;
                    if (is_array($latlng) && count($latlng) >= 2) {
                        $lat = (float) $latlng[0];
                        $lon = (float) $latlng[1];
                    }
                    break;
                }
            }

            if ($lat === null || $lon === null) {
                $sumLat = 0.0;
                $sumLon = 0.0;
                $n = 0;
                foreach ($this->dataset->cities() as $city) {
                    if (!is_array($city) || strtoupper((string) ($city['country_code'] ?? '')) !== $iso2) {
                        continue;
                    }
                    $sumLat += (float) ($city['latitude'] ?? 0.0);
                    $sumLon += (float) ($city['longitude'] ?? 0.0);
                    $n++;
                }
                if ($n > 0) {
                    $lat = $sumLat / $n;
                    $lon = $sumLon / $n;
                } else {
                    $lat = 0.0;
                    $lon = 0.0;
                }
            }

            $centroids[$iso2] = [
                'lat' => (float) $lat,
                'lon' => (float) $lon,
                'name' => $name,
                'iso2' => $iso2,
                'iso3' => $iso3,
            ];
        }

        $this->countryCentroids = $centroids;
        return $centroids;
    }

    /** @return array<string, array{minLat:float,maxLat:float,minLon:float,maxLon:float}> */
    private function countryBboxes(): array
    {
        if ($this->countryBboxes !== null) {
            return $this->countryBboxes;
        }

        $boxes = [];
        foreach ($this->dataset->cities() as $city) {
            if (!is_array($city)) {
                continue;
            }
            $iso2 = strtoupper((string) ($city['country_code'] ?? ''));
            if ($iso2 === '') {
                continue;
            }
            $lat = (float) ($city['latitude'] ?? 0.0);
            $lon = (float) ($city['longitude'] ?? 0.0);
            if (!isset($boxes[$iso2])) {
                $boxes[$iso2] = ['minLat' => $lat, 'maxLat' => $lat, 'minLon' => $lon, 'maxLon' => $lon];
                continue;
            }
            $boxes[$iso2]['minLat'] = min($boxes[$iso2]['minLat'], $lat);
            $boxes[$iso2]['maxLat'] = max($boxes[$iso2]['maxLat'], $lat);
            $boxes[$iso2]['minLon'] = min($boxes[$iso2]['minLon'], $lon);
            $boxes[$iso2]['maxLon'] = max($boxes[$iso2]['maxLon'], $lon);
        }

        $this->countryBboxes = $boxes;
        return $boxes;
    }

    private function norm(string $text): string
    {
        $t = mb_strtolower(UnicodeNormalizer::stripAccents($text));
        $t = (string) preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $t);
        return trim((string) preg_replace('/\s+/u', ' ', $t));
    }

    private function textSimilarity(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($a === $b) {
            return 1.0;
        }
        if (str_contains($b, $a) || str_contains($a, $b)) {
            return 0.92;
        }

        $maxLen = max(strlen($a), strlen($b));
        if ($maxLen <= 0) {
            return 0.0;
        }
        $lev = levenshtein($a, $b);
        return max(0.0, 1.0 - ($lev / $maxLen));
    }
}
