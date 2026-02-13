<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Detect;

use ML\IDEA\NLP\Normalize\UnicodeNormalizer;

final class LanguageDetector
{
    /** @var array<string, array<string, float>> */
    private array $profiles;

    public function __construct(?array $profiles = null)
    {
        $this->profiles = $profiles ?? self::defaultProfiles();
    }

    public function detect(string $text): string
    {
        $scores = $this->rank($text);
        return $scores === [] ? 'unknown' : (string) array_key_first($scores);
    }

    /** @return array{language:string, score:float} */
    public function detectWithScore(string $text): array
    {
        $scores = $this->rank($text);
        if ($scores === []) {
            return ['language' => 'unknown', 'score' => 0.0];
        }

        $lang = (string) array_key_first($scores);
        $score = (float) ($scores[$lang] ?? 0.0);
        return ['language' => $lang, 'score' => $score];
    }

    public function addProfile(string $language, array $profile): self
    {
        $this->profiles[$language] = $profile;
        return $this;
    }

    /** @return array<string, array<string, float>> */
    public function profiles(): array
    {
        return $this->profiles;
    }

    /** @return array<string, float> */
    public function rank(string $text): array
    {
        $freq = $this->ngramProfile($text);
        if ($freq === []) {
            return [];
        }

        $scores = [];
        foreach ($this->profiles as $lang => $profile) {
            $score = 0.0;
            foreach ($freq as $gram => $w) {
                $score += $w * ($profile[$gram] ?? 0.0);
            }
            $scores[$lang] = $score;
        }

        arsort($scores);
        return $scores;
    }

    /** @return array<string, float> */
    private function ngramProfile(string $text): array
    {
        $s = mb_strtolower(UnicodeNormalizer::stripAccents($text));
        $s = (string) preg_replace('/[^\p{L}\s]+/u', ' ', $s);
        $s = trim((string) preg_replace('/\s+/u', ' ', $s));
        if ($s === '') {
            return [];
        }

        $grams = [];
        $chars = preg_split('//u', ' ' . $s . ' ', -1, PREG_SPLIT_NO_EMPTY) ?: [];
        for ($i = 0; $i < count($chars) - 2; $i++) {
            $g = $chars[$i] . $chars[$i + 1] . $chars[$i + 2];
            $grams[$g] = ($grams[$g] ?? 0) + 1;
        }

        $total = array_sum($grams);
        if ($total <= 0) {
            return [];
        }

        foreach ($grams as $g => $c) {
            $grams[$g] = $c / $total;
        }

        arsort($grams);
        return array_slice($grams, 0, 250, true);
    }

    /** @return array<string, array<string, float>> */
    public static function defaultProfiles(): array
    {
        return array_merge([
            'en' => [' th' => 0.08, 'the' => 0.08, 'he ' => 0.06, ' an' => 0.05, 'ing' => 0.04, 'ion' => 0.03],
            'fr' => [' le' => 0.07, ' de' => 0.06, 'ent' => 0.05, 'ion' => 0.04, 'es ' => 0.04, ' la' => 0.04],
            'es' => [' de' => 0.07, ' la' => 0.06, 'que' => 0.06, ' el' => 0.05, ' en' => 0.05, 'los' => 0.03],
            'de' => [' der' => 0.06, ' die' => 0.05, ' und' => 0.05, 'sch' => 0.04, 'ein' => 0.04, 'ich' => 0.03],
            'pt' => [' de' => 0.07, ' que' => 0.06, 'os ' => 0.04, 'ção' => 0.04, ' ent' => 0.03, ' da' => 0.03],
            'it' => [' di' => 0.07, ' che' => 0.06, ' la' => 0.05, ' del' => 0.04, 'zione' => 0.04, ' gli' => 0.03],
            'nl' => [' de' => 0.08, ' het' => 0.06, ' en' => 0.05, ' van' => 0.05, ' sch' => 0.03, ' ij' => 0.03],
        ], self::zambiaProfiles());
    }

    /** @return array<string, array<string, float>> */
    public static function zambiaProfiles(): array
    {
        return [
            // Bemba (bem)
            'bem' => [' ku' => 0.075, ' mu' => 0.068, ' ba' => 0.061, ' pa' => 0.056, ' nga' => 0.048, ' ni ' => 0.045],
            // Nyanja/Chichewa (nya)
            'nya' => [' ndi' => 0.080, ' ku' => 0.062, ' pa' => 0.054, ' mu' => 0.052, ' wa ' => 0.043, ' za ' => 0.039],
            // Tonga (toi)
            'toi' => [' ku' => 0.074, ' ba' => 0.060, ' mu' => 0.056, ' ka' => 0.049, ' na ' => 0.044, 'la ' => 0.038],
            // Lozi (loz)
            'loz' => [' ku' => 0.071, ' ni ' => 0.059, ' li' => 0.054, ' ba' => 0.050, ' mu' => 0.047, ' ya ' => 0.040],
        ];
    }
}
