<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Ner;

use ML\IDEA\Dataset\Services\GeoChunkedIndexBuilder;

final class GeoAwareDisambiguator
{
    public function __construct(
        private readonly ?GeoChunkedIndexBuilder $chunked = null,
    ) {
    }

    /** @param array<int, Entity> $entities @return array<int, Entity> */
    public function disambiguate(
        array $entities,
        string $text,
        ?string $localeHintCountryCode = null,
    ): array {
        $hasCity = false;
        $uniqueCityMentions = [];
        foreach ($entities as $entity) {
            if ($entity->label === 'CITY') {
                $hasCity = true;
                $uniqueCityMentions[mb_strtolower(trim($entity->text))] = true;
            }
        }

        if (!$hasCity || count($uniqueCityMentions) <= 1) {
            return $entities;
        }

        $mentions = [];
        foreach ($entities as $e) {
            $mentions[] = mb_strtolower(trim($e->text));
        }

        $codesInText = [];
        if (preg_match_all('/\+?(\d{1,3})\b/', $text, $m)) {
            $codesInText = $m[1];
        }

        $chunked = $this->chunked ?? new GeoChunkedIndexBuilder();
        $countryByCode = $chunked->countryPhoneIndex();

        $hintCodes = [];
        foreach ($codesInText as $code) {
            $iso2 = $countryByCode[$code] ?? '';
            if ($iso2 !== '') {
                $hintCodes[] = mb_strtolower($iso2);
            }
        }
        if ($localeHintCountryCode !== null) {
            $hintCodes[] = mb_strtolower(trim($localeHintCountryCode));
        }

        $out = [];
        foreach ($entities as $e) {
            $label = $e->label;
            if ($label !== 'CITY') {
                $out[] = $e;
                continue;
            }

            $city = mb_strtolower(trim($e->text));
            $candidates = $chunked->cityNameIndex()[$city] ?? [];

            if (count($candidates) <= 1) {
                $out[] = $e;
                continue;
            }

            $best = $candidates[0];
            $bestScore = -INF;
            foreach ($candidates as $c) {
                $score = 0.0;
                $countryCode = mb_strtolower((string) ($c['country_code'] ?? ''));

                if (in_array($countryCode, $hintCodes, true)) {
                    $score += 2.0;
                }

                foreach ($mentions as $mtext) {
                    if ($mtext === $countryCode) {
                        $score += 2.0;
                    }
                }

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $c;
                }
            }

            $suffix = (string) ($best['country_code'] ?? '');
            $out[] = new Entity($e->text . ($suffix !== '' ? ', ' . $suffix : ''), $e->label, $e->start, $e->end, min(0.98, $e->confidence + 0.08));
        }

        return $out;
    }
}
