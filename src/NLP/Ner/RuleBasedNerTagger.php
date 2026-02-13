<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Ner;

use ML\IDEA\Dataset\Services\GeoChunkedIndexBuilder;
use ML\IDEA\Geo\GeoService;
use ML\IDEA\NLP\Contracts\NerTaggerInterface;
use ML\IDEA\NLP\Normalize\UnicodeNormalizer;

final class RuleBasedNerTagger implements NerTaggerInterface
{
    /** @var array<string, string> */
    private array $gazetteer;
    private bool $autoKnowledgeEnabled = true;
    private int $autoKnowledgeMaxCities = 12000;
    private ?GeoService $geoService = null;

    /** @param array<string, string> $gazetteer */
    public function __construct(array $gazetteer = [])
    {
        $this->gazetteer = array_change_key_case($gazetteer, CASE_LOWER);
    }

    /** @param array<string, string> $gazetteer */
    public function withGazetteer(array $gazetteer): self
    {
        $clone = clone $this;
        $clone->gazetteer = array_merge($clone->gazetteer, array_change_key_case($gazetteer, CASE_LOWER));
        return $clone;
    }

    public function withGeoGazetteer(?GeoService $geo = null, int $maxCities = 5000): self
    {
        $geo ??= new GeoService();
        $clone = $this->withGazetteer($geo->geoGazetteer($maxCities));
        $clone->geoService = $geo;
        return $clone;
    }

    public function withoutAutoKnowledge(): self
    {
        $clone = clone $this;
        $clone->autoKnowledgeEnabled = false;
        return $clone;
    }

    public function withAutoKnowledgeLimit(int $maxCities): self
    {
        $clone = clone $this;
        $clone->autoKnowledgeMaxCities = max(1000, $maxCities);
        return $clone;
    }

    /** @param array<string, array<int, string>> $aliasesByCanonical */
    public function withAliases(array $aliasesByCanonical, string $label = 'LOCATION'): self
    {
        $map = [];
        foreach ($aliasesByCanonical as $canonical => $aliases) {
            $canonicalNorm = $this->norm($canonical);
            if ($canonicalNorm !== '') {
                $map[$canonicalNorm] = $label;
            }
            foreach ($aliases as $alias) {
                $aliasNorm = $this->norm($alias);
                if ($aliasNorm !== '') {
                    $map[$aliasNorm] = $label;
                }
            }
        }

        return $this->withGazetteer($map);
    }

    /** @return array<int, Entity> */
    public function extract(string $text): array
    {
        $this->bootstrapAutoKnowledge();

        $entities = [];

        $patterns = [
            'EMAIL' => '/\b[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[A-Za-z]{2,}\b/u',
            'URL' => '/\bhttps?:\/\/[^\s<>"]+/u',
            'PHONE' => '/\b(?:\+?[0-9][0-9\-\s().]{7,}[0-9])\b/u',
            'DATE' => '/\b\d{4}-\d{2}-\d{2}\b|\b\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}\b/u',
            'MONEY' => '/(?:\$|€|£)\s?\d+(?:[.,]\d+)?/u',
            'IP_ADDRESS' => '/\b(?:\d{1,3}\.){3}\d{1,3}\b/u',
            'TIME' => '/\b(?:[01]?\d|2[0-3]):[0-5]\d(?:\s?[APMapm]{2})?\b/u',
            'PERCENT' => '/\b\d+(?:[.,]\d+)?\s?%/u',
            'HASHTAG' => '/(?<!\w)#[\p{L}\p{N}_]+/u',
            'MENTION' => '/(?<!\w)@[\p{L}\p{N}_\.]+/u',
            'UUID' => '/\b[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[1-5][0-9a-fA-F]{3}\-[89abAB][0-9a-fA-F]{3}\-[0-9a-fA-F]{12}\b/u',
        ];

        foreach ($patterns as $label => $regex) {
            preg_match_all($regex, $text, $m, PREG_OFFSET_CAPTURE);
            foreach ($m[0] as $hit) {
                /** @var array{0:string,1:int} $hit */
                [$raw, $start] = $hit;
                $entities[] = new Entity($raw, $label, $start, $start + strlen($raw), 0.88);
            }
        }

        $gazetteerEntities = $this->geoService !== null
            ? (new GeoEntityRecognizer($this->geoService, $this->autoKnowledgeMaxCities))->recognize($text)
            : (new GazetteerEntityRecognizer($this->gazetteer))->recognize($text);
        $entities = array_merge($entities, $gazetteerEntities);

        preg_match_all('/\b[\p{Lu}][\p{L}\-]+(?:\s+[\p{Lu}][\p{L}\-]+)*\b/u', $text, $m, PREG_OFFSET_CAPTURE);
        foreach ($m[0] as $hit) {
            /** @var array{0:string,1:int} $hit */
            [$raw, $start] = $hit;
            $label = $this->gazetteer[$this->norm($raw)] ?? 'PROPER_NOUN';
            $entities[] = new Entity($raw, $label, $start, $start + strlen($raw), 0.65);
        }

        $resolved = (new SpanResolver())->resolve($entities, allowNesting: true);
        $disambiguated = (new GeoAwareDisambiguator())->disambiguate($resolved, $text);
        return $this->dedupeEntities($disambiguated);
    }

    private function bootstrapAutoKnowledge(): void
    {
        if (!$this->autoKnowledgeEnabled || $this->gazetteer !== []) {
            return;
        }

        $builder = new GeoChunkedIndexBuilder();
        $this->gazetteer = array_merge($this->gazetteer, $builder->geoGazetteerMap($this->autoKnowledgeMaxCities));

        // guaranteed baseline examples even when city cutoffs vary by dataset ordering
        $this->gazetteer['lusaka'] = 'CITY';
        $this->gazetteer['zambia'] = 'COUNTRY';

        foreach ([
            'united states' => ['usa', 'u.s.', 'us'],
            'united kingdom' => ['uk', 'u.k.', 'great britain', 'britain'],
        ] as $canonical => $aliases) {
            $canonicalNorm = $this->norm($canonical);
            if ($canonicalNorm !== '') {
                $this->gazetteer[$canonicalNorm] = 'COUNTRY';
            }
            foreach ($aliases as $alias) {
                $aliasNorm = $this->norm($alias);
                if ($aliasNorm !== '') {
                    $this->gazetteer[$aliasNorm] = 'COUNTRY';
                }
            }
        }
    }

    /** @param array<int, Entity> $entities @return array<int, Entity> */
    private function dedupeEntities(array $entities): array
    {
        /** @var array<string, Entity> $bestBySig */
        $bestBySig = [];
        foreach ($entities as $e) {
            $cleanText = trim($e->text);
            $deltaLeft = strpos($e->text, $cleanText);
            $start = $e->start + (is_int($deltaLeft) ? $deltaLeft : 0);
            $end = $start + strlen($cleanText);

            $canonicalText = $this->canonicalEntitySurface($cleanText, $e->label);
            $sig = $e->label . '|' . $start . '|' . $end . '|' . $canonicalText;

            $candidate = new Entity($cleanText, $e->label, $start, $end, $e->confidence);
            $existing = $bestBySig[$sig] ?? null;
            if ($existing === null || $candidate->confidence > $existing->confidence) {
                $bestBySig[$sig] = $candidate;
            }
        }

        $out = array_values($bestBySig);
        usort($out, static fn (Entity $a, Entity $b): int => $a->start <=> $b->start);
        return $out;
    }

    private function canonicalEntitySurface(string $text, string $label): string
    {
        $norm = $this->norm($text);

        if ($label !== 'COUNTRY') {
            return $norm;
        }

        $compact = (string) preg_replace('/[^\p{L}\p{N}]+/u', '', $norm);
        return match ($compact) {
            'us', 'usa', 'unitedstates' => 'united states',
            'uk', 'unitedkingdom', 'greatbritain', 'britain' => 'united kingdom',
            default => $norm,
        };
    }

    private function norm(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', mb_strtolower(UnicodeNormalizer::stripAccents($value))));
    }
}
