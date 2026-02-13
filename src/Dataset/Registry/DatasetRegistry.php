<?php

declare(strict_types=1);

namespace ML\IDEA\Dataset\Registry;

final class DatasetRegistry
{
    public function __construct(private readonly ?string $basePath = null)
    {
    }

    /** @return array<int, array{name:string, version:string, language:string, schema:string, path:string}> */
    public function listDatasets(): array
    {
        $base = $this->basePath ?? dirname(__DIR__, 2) . '/Dataset';
        return [
            ['name' => 'wordnet', 'version' => '1.0', 'language' => 'en', 'schema' => 'wordnet-json', 'path' => $base . '/wordnet/wn.json'],
            ['name' => 'sentiment', 'version' => '1.0', 'language' => 'en', 'schema' => 'sentiment-json', 'path' => $base . '/sentiment/sentiment_dataset.json'],
            ['name' => 'geo-countries', 'version' => '1.0', 'language' => 'multi', 'schema' => 'countries-json', 'path' => $base . '/geo/countries.json'],
            ['name' => 'geo-countries-states', 'version' => '1.0', 'language' => 'multi', 'schema' => 'countries-states-json', 'path' => $base . '/geo/countries+states.json'],
            ['name' => 'geo-cities', 'version' => '1.0', 'language' => 'multi', 'schema' => 'cities-json', 'path' => $base . '/geo/cities.json'],
            ['name' => 'dictionary-en', 'version' => '1.0', 'language' => 'en', 'schema' => 'word-definition-csv', 'path' => $base . '/dictionary/en/en.csv'],
            ['name' => 'dictionary-en-bemba', 'version' => '1.0', 'language' => 'en,bem', 'schema' => 'translation-csv', 'path' => $base . '/dictionary/bemba/english_to_bemba.csv'],
        ];
    }

    /** @return array<string, array{sha1:string,size:int,exists:bool}> */
    public function integrityReport(): array
    {
        $report = [];
        foreach ($this->listDatasets() as $dataset) {
            $path = $dataset['path'];
            $exists = is_file($path);
            $report[$dataset['name']] = [
                'sha1' => $exists ? sha1_file($path) ?: '' : '',
                'size' => $exists ? (int) filesize($path) : 0,
                'exists' => $exists,
            ];
        }
        return $report;
    }

    /** @return array<string, array{source:string,license:string,usage:string,attribution:string}> */
    public function licenses(): array
    {
        return DatasetLicenses::manifest();
    }
}
