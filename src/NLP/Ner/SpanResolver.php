<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Ner;

final class SpanResolver
{
    /**
     * @param array<int, Entity> $entities
     * @param array<string, int> $priorityByLabel
     * @return array<int, Entity>
     */
    public function resolve(
        array $entities,
        array $priorityByLabel = ['COUNTRY' => 5, 'STATE' => 4, 'CITY' => 4, 'LOCATION' => 4, 'PROPER_NOUN' => 1],
        bool $allowNesting = true,
    ): array {
        usort($entities, static function (Entity $a, Entity $b) use ($priorityByLabel): int {
            $lenA = $a->end - $a->start;
            $lenB = $b->end - $b->start;
            if ($lenA !== $lenB) {
                return $lenB <=> $lenA; // longer first
            }
            $pa = $priorityByLabel[$a->label] ?? 0;
            $pb = $priorityByLabel[$b->label] ?? 0;
            if ($pa !== $pb) {
                return $pb <=> $pa;
            }
            if ($a->confidence !== $b->confidence) {
                return $b->confidence <=> $a->confidence;
            }
            return $a->start <=> $b->start;
        });

        $accepted = [];
        foreach ($entities as $candidate) {
            $overlap = false;
            foreach ($accepted as $existing) {
                if ($this->overlaps($candidate, $existing)) {
                    if ($allowNesting && $this->nested($candidate, $existing)) {
                        continue;
                    }
                    $overlap = true;
                    break;
                }
            }
            if (!$overlap) {
                $accepted[] = $candidate;
            }
        }

        usort($accepted, static fn (Entity $a, Entity $b): int => $a->start <=> $b->start);
        return $accepted;
    }

    private function overlaps(Entity $a, Entity $b): bool
    {
        return $a->start < $b->end && $b->start < $a->end;
    }

    private function nested(Entity $a, Entity $b): bool
    {
        return ($a->start >= $b->start && $a->end <= $b->end)
            || ($b->start >= $a->start && $b->end <= $a->end);
    }
}
