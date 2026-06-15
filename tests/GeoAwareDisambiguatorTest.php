<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Dataset\Services\GeoChunkedIndexBuilder;
use ML\IDEA\NLP\Ner\Entity;
use ML\IDEA\NLP\Ner\GeoAwareDisambiguator;
use PHPUnit\Framework\TestCase;

final class GeoAwareDisambiguatorTest extends TestCase
{
    public function testDisambiguatorLeavesSingleCityMentionUnchanged(): void
    {
        $entities = [
            new Entity('Lusaka', 'CITY', 0, 6, 0.9),
        ];

        $out = (new GeoAwareDisambiguator($this->chunkedIndex()))->disambiguate($entities, 'Travel to Lusaka soon.');

        self::assertCount(1, $out);
        self::assertSame('Lusaka', $out[0]->text);
    }

    public function testDisambiguatorUsesPhoneCodeHintForDuplicateCityNames(): void
    {
        $entities = [
            new Entity('Springfield', 'CITY', 20, 31, 0.9),
            new Entity('Lusaka', 'CITY', 40, 46, 0.9),
        ];

        $out = (new GeoAwareDisambiguator($this->chunkedIndex()))->disambiguate(
            $entities,
            'Please call +260 about Springfield and Lusaka logistics.',
        );

        self::assertCount(2, $out);
        self::assertStringContainsString('ZM', $out[0]->text);
    }

    public function testDisambiguatorUsesExplicitLocaleHint(): void
    {
        $entities = [
            new Entity('Springfield', 'CITY', 0, 11, 0.9),
            new Entity('Lusaka', 'CITY', 20, 26, 0.9),
        ];

        $out = (new GeoAwareDisambiguator($this->chunkedIndex()))->disambiguate(
            $entities,
            'Shipment from Springfield to Lusaka.',
            localeHintCountryCode: 'US',
        );

        self::assertCount(2, $out);
        self::assertStringContainsString('US', $out[0]->text);
    }

    private function chunkedIndex(): GeoChunkedIndexBuilder
    {
        $base = $this->makeTempDir('geo_disambig_');
        mkdir($base . '/geo', 0777, true);

        file_put_contents($base . '/geo/cities.json', json_encode([
            ['name' => 'Lusaka', 'country_code' => 'ZM', 'state_code' => '', 'latitude' => '-15.4167', 'longitude' => '28.2833'],
            ['name' => 'Springfield', 'country_code' => 'US', 'state_code' => 'IL', 'latitude' => '39.7817', 'longitude' => '-89.6501'],
            ['name' => 'Springfield', 'country_code' => 'ZM', 'state_code' => '', 'latitude' => '-13.1333', 'longitude' => '28.3833'],
        ], JSON_THROW_ON_ERROR));

        file_put_contents($base . '/geo/countries+states.json', json_encode([
            ['name' => 'United States', 'iso2' => 'US', 'phone_code' => '1', 'states' => []],
            ['name' => 'Zambia', 'iso2' => 'ZM', 'phone_code' => '260', 'states' => []],
        ], JSON_THROW_ON_ERROR));

        return new GeoChunkedIndexBuilder($base . '/geo', new \ML\IDEA\Dataset\Registry\DatasetCache(sys_get_temp_dir() . '/mlidea_geo_cache_' . uniqid('', true)));
    }

    private function makeTempDir(string $prefix): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . uniqid('', true);
        mkdir($dir, 0777, true);

        return $dir;
    }
}
