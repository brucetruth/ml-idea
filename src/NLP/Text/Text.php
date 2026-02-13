<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Text;

use ML\IDEA\NLP\Detect\LanguageDetector;
use ML\IDEA\NLP\Extract\RakeKeywords;
use ML\IDEA\NLP\Lexicon\SemanticExplorer;
use ML\IDEA\NLP\Ner\Entity;
use ML\IDEA\NLP\Ner\RuleBasedNerTagger;
use ML\IDEA\NLP\Normalize\UnicodeNormalizer;
use ML\IDEA\NLP\Privacy\PIIRedactor;
use ML\IDEA\NLP\Tokenize\SentenceTokenizer;
use ML\IDEA\NLP\Tokenize\UnicodeWordTokenizer;

final readonly class Text
{
    private function __construct(private string $value)
    {
    }

    public static function of(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function normalizeUnicode(string $form = 'NFC'): self
    {
        return new self(UnicodeNormalizer::normalize($this->value, $form));
    }

    public function lower(): self
    {
        return new self(mb_strtolower($this->value));
    }

    public function upper(): self
    {
        return new self(mb_strtoupper($this->value));
    }

    public function title(): self
    {
        return new self(mb_convert_case($this->value, MB_CASE_TITLE, 'UTF-8'));
    }

    public function stripAccents(): self
    {
        return new self(UnicodeNormalizer::stripAccents($this->value));
    }

    public function removeEmoji(): self
    {
        return new self((string) preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]+/u', '', $this->value));
    }

    public function keepEmoji(): self
    {
        preg_match_all('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]+/u', $this->value, $m);
        return new self(implode('', $m[0]));
    }

    public function removePunctuation(): self
    {
        return new self((string) preg_replace('/[\p{P}\p{S}]+/u', ' ', $this->value));
    }

    public function keepPunctuation(): self
    {
        preg_match_all('/[\p{P}\p{S}]+/u', $this->value, $m);
        return new self(implode('', $m[0]));
    }

    public function collapseWhitespace(): self
    {
        $clean = trim((string) preg_replace('/\s+/u', ' ', $this->value));
        return new self($clean);
    }

    public function slug(): self
    {
        $s = UnicodeNormalizer::stripAccents(mb_strtolower($this->value));
        $s = (string) preg_replace('/[^a-z0-9]+/u', '-', $s);
        $s = trim($s, '-');
        return new self($s);
    }

    /** @return array<int, string> */
    public function sentences(): array
    {
        return (new SentenceTokenizer())->split($this->value);
    }

    /** @return array<int, string> */
    public function words(): array
    {
        return array_map(static fn (Token $t): string => $t->text, (new UnicodeWordTokenizer())->tokenize($this->value));
    }

    /** @return array<int, string> */
    public function ngrams(int $n = 2): array
    {
        $words = $this->words();
        if ($n <= 1 || count($words) < $n) {
            return $words;
        }

        $out = [];
        for ($i = 0; $i <= count($words) - $n; $i++) {
            $out[] = implode(' ', array_slice($words, $i, $n));
        }
        return $out;
    }

    /** @return array<int, Token> */
    public function toTokens(): array
    {
        return (new UnicodeWordTokenizer())->tokenize($this->value);
    }

    public function maskPII(): self
    {
        return new self((new PIIRedactor())->redact($this->value));
    }

    public function language(): string
    {
        return (new LanguageDetector())->detect($this->value);
    }

    /** @return array{language:string, score:float} */
    public function languageWithScore(): array
    {
        return (new LanguageDetector())->detectWithScore($this->value);
    }

    public function initials(int $max = 6): string
    {
        $letters = [];
        foreach ($this->words() as $word) {
            $first = mb_substr($word, 0, 1);
            if ($first !== '') {
                $letters[] = mb_strtoupper($first);
            }
            if (count($letters) >= $max) {
                break;
            }
        }

        return implode('', $letters);
    }

    /** @return array<int, array{keyword:string, score:float}> */
    public function keywords(int $topK = 10): array
    {
        return (new RakeKeywords())->extract($this->value, $topK);
    }

    /** @return array<int, Entity> */
    public function entities(?RuleBasedNerTagger $tagger = null): array
    {
        return ($tagger ?? new RuleBasedNerTagger())->extract($this->value);
    }

    /**
     * @return array{
     *   word:string,
     *   definition:?string,
     *   synonyms:array<int,string>,
     *   definitionNeighbors:array<int,string>
     * }
     */
    public function semantics(?SemanticExplorer $explorer = null): array
    {
        $word = trim(mb_strtolower($this->value));
        return ($explorer ?? new SemanticExplorer())->wordInsights($word);
    }
}
