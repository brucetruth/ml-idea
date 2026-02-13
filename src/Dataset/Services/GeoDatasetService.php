<?php

declare(strict_types=1);

namespace ML\IDEA\Dataset\Services;

use ML\IDEA\Dataset\Loaders\JsonDatasetLoader;

final class GeoDatasetService
{
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

        $path = $this->basePath ?? dirname(__DIR__, 2) . '/Dataset/geo';
        $this->countries = (new JsonDatasetLoader())->load($path . '/countries.json');
        return $this->countries;
    }

    /** @return array<int, array<string, mixed>> */
    public function countriesWithStates(): array
    {
        if ($this->countriesWithStates !== null) {
            return $this->countriesWithStates;
        }

        $path = $this->basePath ?? dirname(__DIR__, 2) . '/Dataset/geo';
        $this->countriesWithStates = (new JsonDatasetLoader())->load($path . '/countries+states.json');
        return $this->countriesWithStates;
    }

    /** @return array<int, array<string, mixed>> */
    public function cities(): array
    {
        if ($this->cities !== null) {
            return $this->cities;
        }

        $path = $this->basePath ?? dirname(__DIR__, 2) . '/Dataset/geo';
        $this->cities = (new JsonDatasetLoader())->load($path . '/cities.json');
        return $this->cities;
    }
}
