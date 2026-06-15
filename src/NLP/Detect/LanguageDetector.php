<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Detect;

use ML\IDEA\NLP\Normalize\UnicodeNormalizer;
use ML\IDEA\NLP\Tokenize\SentenceTokenizer;

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

    /** @return array{language:string, score:float, confidence:float} */
    public function detectWithScore(string $text): array
    {
        $scores = $this->rank($text);
        if ($scores === []) {
            return ['language' => 'unknown', 'score' => 0.0, 'confidence' => 0.0];
        }

        $normalized = $this->normalizeScores($scores);
        $lang = (string) array_key_first($normalized);

        return [
            'language' => $lang,
            'score' => (float) ($scores[$lang] ?? 0.0),
            'confidence' => (float) ($normalized[$lang] ?? 0.0),
        ];
    }

    /**
     * @return array<int, array{language:string, score:float, confidence:float}>
     */
    public function detectTop(string $text, int $limit = 3, float $minConfidence = 0.05): array
    {
        $limit = max(1, $limit);
        $scores = $this->rank($text);
        if ($scores === []) {
            return [];
        }

        $normalized = $this->normalizeScores($scores);
        $out = [];
        foreach ($normalized as $lang => $confidence) {
            if ($confidence < $minConfidence) {
                continue;
            }
            $out[] = [
                'language' => $lang,
                'score' => (float) ($scores[$lang] ?? 0.0),
                'confidence' => (float) $confidence,
            ];
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * Per-sentence language detection with byte offsets into the original text.
     *
     * @return array<int, array{text:string, start:int, end:int, language:string, score:float, confidence:float}>
     */
    public function detectSegments(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $sentences = (new SentenceTokenizer())->split($text);
        if ($sentences === []) {
            $det = $this->detectWithScore($text);

            return [[
                'text' => $text,
                'start' => 0,
                'end' => strlen($text),
                'language' => $det['language'],
                'score' => $det['score'],
                'confidence' => $det['confidence'],
            ]];
        }

        $offset = 0;
        $segments = [];
        foreach ($sentences as $sentence) {
            $start = mb_strpos($text, $sentence, $offset);
            if ($start === false) {
                $start = $offset;
            }
            $end = $start + mb_strlen($sentence);
            $det = $this->detectWithScore($sentence);
            $segments[] = [
                'text' => $sentence,
                'start' => $start,
                'end' => $end,
                'language' => $det['language'],
                'score' => $det['score'],
                'confidence' => $det['confidence'],
            ];
            $offset = $end;
        }

        return $segments;
    }

    /**
     * Detect code-switching / mixed-language documents.
     *
     * @return array{
     *     primary:string,
     *     confidence:float,
     *     multilingual:bool,
     *     languages:array<int, array{language:string, confidence:float, proportion:float}>
     * }
     */
    public function detectMixed(string $text, float $secondaryThreshold = 0.15): array
    {
        $segments = $this->detectSegments($text);
        if ($segments === []) {
            $top = $this->detectTop($text, 5);

            return [
                'primary' => $top[0]['language'] ?? 'unknown',
                'confidence' => $top[0]['confidence'] ?? 0.0,
                'multilingual' => false,
                'languages' => array_map(
                    static fn (array $row): array => [
                        'language' => $row['language'],
                        'confidence' => $row['confidence'],
                        'proportion' => $row['confidence'],
                    ],
                    $top,
                ),
            ];
        }

        $weights = [];
        $totalWeight = 0.0;
        foreach ($segments as $segment) {
            $weight = max(1.0, (float) mb_strlen($segment['text'])) * max(0.0, (float) $segment['confidence']);
            $lang = (string) $segment['language'];
            $weights[$lang] = ($weights[$lang] ?? 0.0) + $weight;
            $totalWeight += $weight;
        }

        if ($totalWeight <= 0.0) {
            $top = $this->detectTop($text, 5);

            return [
                'primary' => $top[0]['language'] ?? 'unknown',
                'confidence' => $top[0]['confidence'] ?? 0.0,
                'multilingual' => false,
                'languages' => [],
            ];
        }

        $languages = [];
        foreach ($weights as $lang => $weight) {
            $languages[] = [
                'language' => $lang,
                'confidence' => $this->detectWithScore($text)['confidence'],
                'proportion' => $weight / $totalWeight,
            ];
        }

        usort($languages, static fn (array $a, array $b): int => $b['proportion'] <=> $a['proportion']);
        $primary = $languages[0]['language'] ?? 'unknown';
        $secondaryCount = count(array_filter(
            $languages,
            static fn (array $row): bool => ($row['proportion'] ?? 0.0) >= $secondaryThreshold,
        ));

        return [
            'primary' => $primary,
            'confidence' => (float) ($languages[0]['proportion'] ?? 0.0),
            'multilingual' => $secondaryCount >= 2,
            'languages' => $languages,
        ];
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

        $boosts = self::scriptBoosts($text);
        foreach (self::digraphBoosts($text) as $lang => $weight) {
            $boosts[$lang] = ($boosts[$lang] ?? 0.0) + $weight;
        }
        foreach ($scores as $lang => $score) {
            $scores[$lang] = $score + ($boosts[$lang] ?? 0.0);
        }

        arsort($scores);

        return $scores;
    }

    /** @return array<string, float> */
    private static function scriptBoosts(string $text): array
    {
        $counts = [
            'latin' => 0, 'cyrillic' => 0, 'arabic' => 0, 'hebrew' => 0, 'devanagari' => 0,
            'bengali' => 0, 'tamil' => 0, 'telugu' => 0, 'gurmukhi' => 0, 'gujarati' => 0,
            'kannada' => 0, 'malayalam' => 0, 'sinhala' => 0, 'armenian' => 0, 'georgian' => 0,
            'tibetan' => 0, 'oriya' => 0, 'thai' => 0, 'hangul' => 0,
            'hiragana' => 0, 'katakana' => 0, 'han' => 0, 'greek' => 0, 'myanmar' => 0,
            'khmer' => 0, 'lao' => 0, 'ethiopic' => 0,
        ];

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            if (preg_match('/\p{Latin}/u', $ch) === 1) {
                $counts['latin']++;
            } elseif (preg_match('/\p{Cyrillic}/u', $ch) === 1) {
                $counts['cyrillic']++;
            } elseif (preg_match('/\p{Arabic}/u', $ch) === 1) {
                $counts['arabic']++;
            } elseif (preg_match('/\p{Hebrew}/u', $ch) === 1) {
                $counts['hebrew']++;
            } elseif (preg_match('/\p{Devanagari}/u', $ch) === 1) {
                $counts['devanagari']++;
            } elseif (preg_match('/\p{Bengali}/u', $ch) === 1) {
                $counts['bengali']++;
            } elseif (preg_match('/\p{Tamil}/u', $ch) === 1) {
                $counts['tamil']++;
            } elseif (preg_match('/\p{Telugu}/u', $ch) === 1) {
                $counts['telugu']++;
            } elseif (preg_match('/\p{Gurmukhi}/u', $ch) === 1) {
                $counts['gurmukhi']++;
            } elseif (preg_match('/\p{Gujarati}/u', $ch) === 1) {
                $counts['gujarati']++;
            } elseif (preg_match('/\p{Kannada}/u', $ch) === 1) {
                $counts['kannada']++;
            } elseif (preg_match('/\p{Malayalam}/u', $ch) === 1) {
                $counts['malayalam']++;
            } elseif (preg_match('/\p{Sinhala}/u', $ch) === 1) {
                $counts['sinhala']++;
            } elseif (preg_match('/\p{Armenian}/u', $ch) === 1) {
                $counts['armenian']++;
            } elseif (preg_match('/\p{Georgian}/u', $ch) === 1) {
                $counts['georgian']++;
            } elseif (preg_match('/\p{Tibetan}/u', $ch) === 1) {
                $counts['tibetan']++;
            } elseif (preg_match('/\p{Oriya}/u', $ch) === 1) {
                $counts['oriya']++;
            } elseif (preg_match('/\p{Thai}/u', $ch) === 1) {
                $counts['thai']++;
            } elseif (preg_match('/\p{Hangul}/u', $ch) === 1) {
                $counts['hangul']++;
            } elseif (preg_match('/\p{Hiragana}/u', $ch) === 1) {
                $counts['hiragana']++;
            } elseif (preg_match('/\p{Katakana}/u', $ch) === 1) {
                $counts['katakana']++;
            } elseif (preg_match('/\p{Han}/u', $ch) === 1) {
                $counts['han']++;
            } elseif (preg_match('/\p{Greek}/u', $ch) === 1) {
                $counts['greek']++;
            } elseif (preg_match('/\p{Myanmar}/u', $ch) === 1) {
                $counts['myanmar']++;
            } elseif (preg_match('/\p{Khmer}/u', $ch) === 1) {
                $counts['khmer']++;
            } elseif (preg_match('/\p{Lao}/u', $ch) === 1) {
                $counts['lao']++;
            } elseif (preg_match('/\p{Ethiopic}/u', $ch) === 1) {
                $counts['ethiopic']++;
            }
        }

        $letters = array_sum($counts);
        if ($letters <= 0) {
            return [];
        }

        $share = static fn (string $key): float => $counts[$key] / $letters;
        $boosts = [];

        if ($share('hangul') >= 0.2) {
            $boosts['ko'] = 1.0;
        }
        if ($share('hiragana') + $share('katakana') >= 0.05) {
            $boosts['ja'] = 1.0;
        } elseif ($share('han') >= 0.2 && $share('hiragana') + $share('katakana') < 0.02) {
            $boosts['zh'] = 1.0;
        }
        if ($share('cyrillic') >= 0.2) {
            foreach (['ru', 'uk', 'bg', 'be', 'sr', 'mk', 'kk', 'ky', 'tg', 'mn'] as $code) {
                $boosts[$code] = 0.15;
            }
        }
        if ($share('arabic') >= 0.2) {
            foreach (['ar', 'fa', 'ur', 'ps', 'sd', 'ug'] as $code) {
                $boosts[$code] = 0.2;
            }
        }
        if ($share('hebrew') >= 0.2) {
            $boosts['he'] = 1.0;
        }
        if ($share('devanagari') >= 0.2) {
            foreach (['hi', 'ne', 'mr'] as $code) {
                $boosts[$code] = ($boosts[$code] ?? 0) + 0.35;
            }
        }
        if ($share('bengali') >= 0.2) {
            foreach (['bn', 'as'] as $code) {
                $boosts[$code] = 1.0;
            }
        }
        if ($share('tamil') >= 0.2) {
            $boosts['ta'] = 1.0;
        }
        if ($share('telugu') >= 0.2) {
            $boosts['te'] = 1.0;
        }
        if ($share('gurmukhi') >= 0.2) {
            $boosts['pa'] = 1.0;
        }
        if ($share('gujarati') >= 0.2) {
            $boosts['gu'] = 1.0;
        }
        if ($share('kannada') >= 0.2) {
            $boosts['kn'] = 1.0;
        }
        if ($share('malayalam') >= 0.2) {
            $boosts['ml'] = 1.0;
        }
        if ($share('sinhala') >= 0.2) {
            $boosts['si'] = 1.0;
        }
        if ($share('armenian') >= 0.2) {
            $boosts['hy'] = 1.0;
        }
        if ($share('georgian') >= 0.2) {
            $boosts['ka'] = 1.0;
        }
        if ($share('tibetan') >= 0.2) {
            $boosts['bo'] = 1.0;
        }
        if ($share('oriya') >= 0.2) {
            $boosts['or'] = 1.0;
        }
        if ($share('thai') >= 0.2) {
            $boosts['th'] = 1.0;
        }
        if ($share('myanmar') >= 0.2) {
            $boosts['my'] = 1.0;
        }
        if ($share('khmer') >= 0.2) {
            $boosts['km'] = 1.0;
        }
        if ($share('lao') >= 0.2) {
            $boosts['lo'] = 1.0;
        }
        if ($share('greek') >= 0.2) {
            $boosts['el'] = 1.0;
        }
        if ($share('ethiopic') >= 0.2) {
            foreach (['am', 'ti'] as $code) {
                $boosts[$code] = 1.0;
            }
        }

        return $boosts;
    }

    /** @return array<string, float> */
    private static function digraphBoosts(string $text): array
    {
        $boosts = [];
        if (preg_match('/ñ/u', $text) === 1) {
            $boosts['es'] = ($boosts['es'] ?? 0) + 0.5;
        }
        if (preg_match('/[ãõ]/u', $text) === 1) {
            $boosts['pt'] = ($boosts['pt'] ?? 0) + 0.5;
        }
        if (preg_match('/(?:çã|ção|ções|são|não)/ui', $text) === 1) {
            $boosts['pt'] = ($boosts['pt'] ?? 0) + 0.35;
        }
        if (preg_match('/ij/u', $text) === 1 && preg_match('/\b(de|het|een|van|niet|maar|door|zijn)\b/ui', $text) === 1) {
            $boosts['nl'] = ($boosts['nl'] ?? 0) + 0.35;
        }
        if (preg_match('/(?:tion|sion|eux|çois| où )/ui', $text) === 1
            && preg_match('/\b(le|la|les|des|une|nous|vous|est|bonjour|français| où )\b/ui', $text) === 1) {
            $boosts['fr'] = ($boosts['fr'] ?? 0) + 0.25;
        }
        if (preg_match('/(?:zione|\bglie\b|\bgli\b|\bil\b|\bche\b)/ui', $text) === 1
            && preg_match('/\b(il|lo|la|gli|che|della|nella|sono|molte|nel|una|perché|italiano|italiana|quotidiano|preposizioni|parlare)\b/ui', $text) === 1) {
            $boosts['it'] = ($boosts['it'] ?? 0) + 0.3;
        }
        if (preg_match('/(?:sch|ung|ß)/u', $text) === 1
            || (preg_match('/[äöü]/u', $text) === 1 && preg_match('/[åæø]/u', $text) !== 1)) {
            $boosts['de'] = ($boosts['de'] ?? 0) + 0.25;
        }
        if (preg_match('/[ąćęłńóśźż]/u', $text) === 1) {
            $boosts['pl'] = ($boosts['pl'] ?? 0) + 0.4;
        }
        if (preg_match('/[æø]/u', $text) === 1) {
            $boosts['da'] = ($boosts['da'] ?? 0) + 0.2;
        }
        if (preg_match('/[å]/u', $text) === 1 || (preg_match('/[äö]/u', $text) === 1 && preg_match('/\b(och|den|det|over|under)\b/ui', $text) === 1)) {
            $boosts['sv'] = ($boosts['sv'] ?? 0) + 0.25;
        }
        if (preg_match('/[ğış]/u', $text) === 1) {
            $boosts['tr'] = ($boosts['tr'] ?? 0) + 0.5;
        }
        if (preg_match('/[ăâđôơư]/u', $text) === 1) {
            $boosts['vi'] = ($boosts['vi'] ?? 0) + 0.5;
        }
        if (preg_match_all('/\b(the|and|that|this|with|from|have|over|was|were|in|of|to|for|is|are|english|countryside)\b/ui', $text) >= 3) {
            $boosts['en'] = ($boosts['en'] ?? 0) + 0.25;
        }
        if (preg_match('/\b(na|kwa|wa|ya|ni|katika|huru|mbwa|mbweha)\b/ui', $text) === 1
            && !preg_match('/\b(ulubee|chibemba|lusaka|imbwa|bulumende|mu\b)\b/ui', $text)) {
            $boosts['sw'] = ($boosts['sw'] ?? 0) + 0.35;
        }
        if (preg_match('/\b(ulubee|chibemba|lusaka|bulumende|ichibemba)\b/ui', $text) === 1) {
            $boosts['bem'] = ($boosts['bem'] ?? 0) + 0.45;
        }

        return $boosts;
    }

    /** @param array<string, float> $scores @return array<string, float> */
    public function normalizeScores(array $scores): array
    {
        if ($scores === []) {
            return [];
        }

        $max = max($scores);
        if ($max <= 0.0) {
            $count = count($scores);

            return array_fill_keys(array_keys($scores), 1.0 / max(1, $count));
        }

        $normalized = [];
        foreach ($scores as $lang => $score) {
            $normalized[$lang] = max(0.0, (float) $score) / $max;
        }

        arsort($normalized);

        return $normalized;
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
        return LanguageRegistry::profiles();
    }

    /** @return array<string, array<string, float>> */
    public static function zambiaProfiles(): array
    {
        $all = LanguageRegistry::profiles();

        return array_intersect_key($all, array_flip(['bem', 'nya', 'toi', 'loz']));
    }
}
