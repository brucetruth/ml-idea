<?php

declare(strict_types=1);

namespace ML\IDEA\Dataset\Services;

use ML\IDEA\Dataset\Loaders\JsonDatasetLoader;
use ML\IDEA\Dataset\Registry\DatasetPaths;
use ML\IDEA\Exceptions\InvalidArgumentException;

final class GeoDatasetService
{
    private const FULL_CITY_LOAD_MAX_BYTES = 4_000_000;
    /** @var array<int, array<string, mixed>>|null */
    private ?array $countries = null;
    /** @var array<int, array<string, mixed>>|null */
    private ?array $countriesWithStates = null;
    /** @var array<int, array<string, mixed>>|null */
    private ?array $cities = null;

    public function __construct(private readonly ?string $basePath = null)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function countries(): array
    {
        if ($this->countries !== null) {
            return $this->countries;
        }

        $path = $this->basePath ?? DatasetPaths::resolve('geo');
        $this->countries = (new JsonDatasetLoader())->load($path . '/countries.json');
        return $this->countries;
    }

    /** @return array<int, array<string, mixed>> */
    public function countriesWithStates(): array
    {
        if ($this->countriesWithStates !== null) {
            return $this->countriesWithStates;
        }

        $path = $this->basePath ?? DatasetPaths::resolve('geo');
        $this->countriesWithStates = (new JsonDatasetLoader())->load($path . '/countries+states.json');
        return $this->countriesWithStates;
    }

    /** @return array<int, array<string, mixed>> */
    public function cities(): array
    {
        if ($this->cities !== null) {
            return $this->cities;
        }

        $path = $this->geoFilePath('cities.json');
        if (is_file($path) && filesize($path) > self::FULL_CITY_LOAD_MAX_BYTES) {
            throw new InvalidArgumentException(
                'The cities dataset is too large to materialize fully in memory. Use streamCities() instead.'
            );
        }

        $this->cities = (new JsonDatasetLoader())->load($path);
        return $this->cities;
    }

    /** @return \Generator<int, array<string, mixed>> */
    public function streamCities(): \Generator
    {
        yield from (new GeoChunkedIndexBuilder($this->geoBasePath()))
            ->streamObjects($this->geoFilePath('cities.json'));
    }

    private function geoBasePath(): string
    {
        return $this->basePath ?? DatasetPaths::resolve('geo');
    }

    private function geoFilePath(string $file): string
    {
        return rtrim($this->geoBasePath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;
    }
}
