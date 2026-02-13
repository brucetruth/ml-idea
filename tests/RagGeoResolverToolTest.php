<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Dataset\Services\GeoDatasetService;
use ML\IDEA\Geo\GeoService;
use ML\IDEA\RAG\Tools\GeoResolverTool;
use PHPUnit\Framework\TestCase;

final class RagGeoResolverToolTest extends TestCase
{
    public function testGeoResolverToolCanResolvePlaceName(): void
    {
        $base = $this->makeGeoFixture();
        $tool = new GeoResolverTool(new GeoService(new GeoDatasetService($base . '/geo')));

        $out = $tool->invoke(['place' => 'Lusaka']);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($out, true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue((bool) ($decoded['ok'] ?? false));
        self::assertSame('CITY', $decoded['place_resolution']['normalized']['type'] ?? null);
        self::assertSame('Lusaka', $decoded['place_resolution']['normalized']['name'] ?? null);
        self::assertSame('Zambia', $decoded['place_resolution']['normalized']['country_name'] ?? null);
    }

    public function testGeoResolverToolCanReverseGeocodeCoordinates(): void
    {
        $base = $this->makeGeoFixture();
        $tool = new GeoResolverTool(new GeoService(new GeoDatasetService($base . '/geo')));

        $out = $tool->invoke(['lat' => -15.4167, 'lon' => 28.2833]);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($out, true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue((bool) ($decoded['ok'] ?? false));
        self::assertSame('Lusaka', $decoded['reverse_geocode']['nearest_city']['name'] ?? null);
        self::assertSame('Zambia', $decoded['reverse_geocode']['country']['name'] ?? null);
    }

    private function makeGeoFixture(): string
    {
        $base = sys_get_temp_dir() . '/mlidea_geo_tool_' . uniqid('', true);
        mkdir($base . '/geo', 0777, true);

        file_put_contents($base . '/geo/countries+states.json', json_encode([
            ['name' => 'Zambia', 'iso2' => 'ZM', 'iso3' => 'ZMB', 'states' => [['name' => 'Lusaka Province']]],
            ['name' => 'Kenya', 'iso2' => 'KE', 'iso3' => 'KEN', 'states' => []],
        ], JSON_THROW_ON_ERROR));

        file_put_contents($base . '/geo/countries.json', json_encode([
            ['cca2' => 'ZM', 'name' => ['common' => 'Zambia'], 'latlng' => [-13.0, 28.0]],
            ['cca2' => 'KE', 'name' => ['common' => 'Kenya'], 'latlng' => [1.0, 38.0]],
        ], JSON_THROW_ON_ERROR));

        file_put_contents($base . '/geo/cities.json', json_encode([
            ['name' => 'Lusaka', 'country_code' => 'ZM', 'state_code' => 'LUS', 'latitude' => '-15.4167', 'longitude' => '28.2833'],
            ['name' => 'Nairobi', 'country_code' => 'KE', 'state_code' => 'NA', 'latitude' => '-1.286389', 'longitude' => '36.817223'],
        ], JSON_THROW_ON_ERROR));

        return $base;
    }
}
