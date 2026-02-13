<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Ner;

use ML\IDEA\Geo\GeoService;

final class GeoEntityRecognizer
{
    private GazetteerEntityRecognizer $gazetteer;

    public function __construct(
        private readonly GeoService $geo,
        private readonly int $maxCities = 12000,
    ) {
        $this->gazetteer = new GazetteerEntityRecognizer($geo->geoGazetteer($maxCities));
    }

    /** @return array<int, Entity> */
    public function recognize(string $text): array
    {
        $entities = $this->gazetteer->recognize($text);
        $out = [];

        foreach ($entities as $entity) {
            $best = $this->geo->searchPlace($entity->text, 1)[0] ?? null;
            if (!is_array($best)) {
                $out[] = $entity;
                continue;
            }

            $out[] = new Entity(
                text: $entity->text,
                label: $entity->label,
                start: $entity->start,
                end: $entity->end,
                confidence: max($entity->confidence, (float) ($best['confidence'] ?? 0.0)),
            );
        }

        return $out;
    }
}
