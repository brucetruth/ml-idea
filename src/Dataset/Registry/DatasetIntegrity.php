<?php

declare(strict_types=1);

namespace ML\IDEA\Dataset\Registry;

final class DatasetIntegrity
{
    /** @return array<string, array{sha1:string,size:int,exists:bool}> */
    public static function seedReport(): array
    {
        $root = dirname(__DIR__, 2) . '/datasets';

        return (new DatasetRegistry($root))->integrityReport();
    }

    /** @return array<int, string> Missing dataset names from the seed tree. */
    public static function missingSeedDatasets(): array
    {
        $missing = [];
        foreach (self::seedReport() as $name => $meta) {
            if (!($meta['exists'] ?? false)) {
                $missing[] = $name;
            }
        }

        return $missing;
    }

    public static function seedsAreComplete(): bool
    {
        return self::missingSeedDatasets() === [];
    }
}
