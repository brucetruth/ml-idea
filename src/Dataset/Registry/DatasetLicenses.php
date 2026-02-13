<?php

declare(strict_types=1);

namespace ML\IDEA\Dataset\Registry;

final class DatasetLicenses
{
    /** @return array<string, array{source:string, license:string, usage:string, attribution:string}> */
    public static function manifest(): array
    {
        return [
            'wordnet' => [
                'source' => 'WordNet JSON export bundled in project',
                'license' => 'WordNet License',
                'usage' => 'Lookup, lexical expansion, semantic utilities',
                'attribution' => 'Princeton University WordNet',
            ],
            'sentiment' => [
                'source' => 'Bundled sentiment_dataset.json',
                'license' => 'Project dataset license (verify for redistribution)',
                'usage' => 'Model training and evaluation',
                'attribution' => 'ml-idea dataset contributors',
            ],
            'geo' => [
                'source' => 'Bundled geo JSON files',
                'license' => 'Source-specific license (verify for commercial use)',
                'usage' => 'NER gazetteers, geo lookup, nearest city search',
                'attribution' => 'Original geo data providers',
            ],
            'dictionary_en' => [
                'source' => 'Bundled en.csv dictionary',
                'license' => 'Source-specific dictionary license',
                'usage' => 'Definitions and reverse meaning lookup',
                'attribution' => 'Dictionary data source maintainers',
            ],
            'dictionary_bemba' => [
                'source' => 'Bundled english_to_bemba.csv',
                'license' => 'Project/local dictionary license',
                'usage' => 'EN↔BEM lexical translation utilities',
                'attribution' => 'Bemba dictionary contributors',
            ],
        ];
    }
}
