<?php

declare(strict_types=1);

namespace ML\IDEA\Geo;

final class GeoFeatureBuilder
{
    public function __construct(private readonly GeoService $geo = new GeoService())
    {
    }

    /** @return array<int, float> */
    public function buildForCoordinate(float $latitude, float $longitude): array
    {
        $nearest = $this->geo->nearestCity($latitude, $longitude);
        $country = $this->geo->countryFromCoordinates($latitude, $longitude);

        $nearestKm = (float) ($nearest['distance_km'] ?? 0.0);
        $countryCode = (string) ($country['cca2'] ?? '');

        return [
            $latitude,
            $longitude,
            $nearestKm,
            $this->countryCodeNumeric($countryCode),
        ];
    }

    /**
     * @param array<int, array{0:float,1:float}> $coordinates
     * @return array<int, array<int, float>>
     */
    public function buildBatch(array $coordinates): array
    {
        $out = [];
        foreach ($coordinates as [$lat, $lon]) {
            $out[] = $this->buildForCoordinate((float) $lat, (float) $lon);
        }

        return $out;
    }

    private function countryCodeNumeric(string $code): float
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return 0.0;
        }

        $sum = 0;
        for ($i = 0; $i < strlen($code); $i++) {
            $sum += ord($code[$i]);
        }

        return $sum / 200.0;
    }
}
