<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Tools;

use ML\IDEA\Geo\GeoService;
use ML\IDEA\RAG\Contracts\ToolInterface;

final class GeoResolverTool implements ToolInterface
{
    public function __construct(private readonly ?GeoService $geo = null)
    {
    }

    public function name(): string
    {
        return 'geo_resolver';
    }

    public function description(): string
    {
        return 'Resolve place names to normalized city/country and reverse geocode coordinates.';
    }

    public function invoke(array $input): string
    {
        $geo = $this->geo ?? new GeoService();

        $payload = [
            'ok' => true,
            'query' => [
                'place' => isset($input['place']) ? (string) $input['place'] : null,
                'lat' => isset($input['lat']) ? (float) $input['lat'] : null,
                'lon' => isset($input['lon']) ? (float) $input['lon'] : null,
            ],
            'place_resolution' => null,
            'reverse_geocode' => null,
        ];

        if (is_string($input['place'] ?? null) && trim((string) $input['place']) !== '') {
            $query = trim((string) $input['place']);
            $hits = $geo->searchPlace($query, 5);
            $best = $hits[0] ?? null;
            if (is_array($best)) {
                $country = null;
                if (($best['country_code'] ?? '') !== '') {
                    $country = $geo->country((string) $best['country_code']);
                }

                $payload['place_resolution'] = [
                    'query' => $query,
                    'normalized' => [
                        'type' => (string) ($best['type'] ?? ''),
                        'name' => (string) ($best['name'] ?? ''),
                        'country_code' => (string) ($best['country_code'] ?? ''),
                        'country_name' => (string) (($country['name'] ?? '') ?: ''),
                        'state_code' => (string) ($best['state_code'] ?? ''),
                        'confidence' => (float) ($best['confidence'] ?? 0.0),
                    ],
                    'candidates' => $hits,
                ];
            }
        }

        $hasLatLon = isset($input['lat'], $input['lon']) && is_numeric($input['lat']) && is_numeric($input['lon']);
        if ($hasLatLon) {
            $lat = (float) $input['lat'];
            $lon = (float) $input['lon'];
            $nearestCity = $geo->nearestCity($lat, $lon);
            $country = $geo->countryFromCoordinates($lat, $lon);

            $payload['reverse_geocode'] = [
                'input' => ['lat' => $lat, 'lon' => $lon],
                'nearest_city' => $nearestCity,
                'country' => $country,
            ];
        }

        if ($payload['place_resolution'] === null && $payload['reverse_geocode'] === null) {
            $payload['ok'] = false;
            $payload['error'] = 'Provide either {"place":"..."} or {"lat":...,"lon":...}';
        }

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }
}
