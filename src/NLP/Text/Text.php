<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Text;

use ML\IDEA\NLP\Contracts\NerTaggerInterface;
use ML\IDEA\NLP\Contracts\PosTaggerInterface;
use ML\IDEA\NLP\Contracts\TokenizerInterface;
use ML\IDEA\NLP\Contracts\TranslatorInterface;
use ML\IDEA\NLP\Detect\LanguageDetector;
use ML\IDEA\NLP\Doc\Doc;
use ML\IDEA\NLP\Extract\RakeKeywords;
use ML\IDEA\NLP\Extract\Stopwords;
use ML\IDEA\NLP\Lexicon\SemanticExplorer;
use ML\IDEA\NLP\Language;
use ML\IDEA\NLP\Nlp;
use ML\IDEA\NLP\Ner\Entity;
use ML\IDEA\NLP\Ner\RuleBasedNerTagger;
use ML\IDEA\NLP\Pos\RuleBasedPosTagger;
use ML\IDEA\NLP\Normalize\UnicodeNormalizer;
use ML\IDEA\NLP\Privacy\PIIRedactor;
use ML\IDEA\NLP\Privacy\SensitiveTermFilter;
use ML\IDEA\NLP\Sentiment\SentimentAnalyzer;
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
    public function words(?TokenizerInterface $tokenizer = null): array
    {
        $tok = $tokenizer ?? new UnicodeWordTokenizer();

        return array_map(static fn (Token $t): string => $t->text, $tok->tokenize($this->value));
    }

    /** @return array<int, string> */
    public function wordsWithoutStopwords(string $language = 'en', ?TokenizerInterface $tokenizer = null): array
    {
        return Stopwords::filter($this->words($tokenizer), $language);
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
    public function toTokens(?TokenizerInterface $tokenizer = null): array
    {
        return ($tokenizer ?? new UnicodeWordTokenizer())->tokenize($this->value);
    }

    public function maskPII(): self
    {
        return new self((new PIIRedactor())->redact($this->value));
    }

    /**
     * @param array<int, string> $terms
     */
    public function maskSensitiveTerms(array $terms, bool $fuzzy = false, int $maxDistance = 1, string $mask = '[SENSITIVE]'): self
    {
        return new self((new SensitiveTermFilter($terms, $fuzzy, $maxDistance))->redact($this->value, $mask));
    }

    /** @return array<int, string> */
    public function findSensitiveTerms(array $terms, bool $fuzzy = false, int $maxDistance = 1): array
    {
        return (new SensitiveTermFilter($terms, $fuzzy, $maxDistance))->find($this->value);
    }

    public function language(): string
    {
        return (new LanguageDetector())->detect($this->value);
    }

    /** @return array{language:string, score:float, confidence:float} */
    public function languageWithScore(): array
    {
        return (new LanguageDetector())->detectWithScore($this->value);
    }

    /** @return array<int, array{language:string, score:float, confidence:float}> */
    public function languageTop(int $limit = 3, float $minConfidence = 0.05): array
    {
        return (new LanguageDetector())->detectTop($this->value, $limit, $minConfidence);
    }

    /** @return array<int, array{text:string, start:int, end:int, language:string, score:float, confidence:float}> */
    public function languageSegments(): array
    {
        return (new LanguageDetector())->detectSegments($this->value);
    }

    /**
     * @return array{
     *     primary:string,
     *     confidence:float,
     *     multilingual:bool,
     *     languages:array<int, array{language:string, confidence:float, proportion:float}>
     * }
     */
    public function languageMixed(float $secondaryThreshold = 0.15): array
    {
        return (new LanguageDetector())->detectMixed($this->value, $secondaryThreshold);
    }

    public function toDoc(?Language $nlp = null): Doc
    {
        $language = $nlp ?? Nlp::blank($this->language());

        return $language->process($this->value);
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
    public function entities(NerTaggerInterface|RuleBasedNerTagger|null $tagger = null, ?NlpPipeline $pipeline = null): array
    {
        $resolved = $tagger ?? $pipeline?->nerTagger() ?? new RuleBasedNerTagger();

        return $resolved->extract($this->value);
    }

    /** @return array<int, array{token: Token, pos: string}> */
    public function pos(?PosTaggerInterface $tagger = null, ?NlpPipeline $pipeline = null): array
    {
        $resolved = $tagger ?? $pipeline?->posTagger() ?? new RuleBasedPosTagger('en');
        $tokens = $this->toTokens($pipeline?->tokenizer());

        return $resolved->tag($tokens);
    }

    public function translate(?TranslatorInterface $translator = null, ?NlpPipeline $pipeline = null): string
    {
        $resolved = $translator ?? $pipeline?->translator();
        if ($resolved === null) {
            throw new \RuntimeException('No translator configured. Pass a TranslatorInterface or use NlpPipeline::forLanguage().');
        }

        return $resolved->translate($this->value);
    }

    /** @return array{label:string, negative:float, positive:float, neutral:float} */
    public function sentiment(?SentimentAnalyzer $analyzer = null, ?NlpPipeline $pipeline = null): array
    {
        $resolved = $analyzer ?? $pipeline?->sentimentAnalyzer() ?? new SentimentAnalyzer();
        $proba = $resolved->predictProba($this->value);

        return [
            'label' => $resolved->predict($this->value),
            'negative' => $proba['negative'],
            'positive' => $proba['positive'],
            'neutral' => $proba['neutral'],
        ];
    }

    public function pipelineForDetectedLanguage(): NlpPipeline
    {
        return NlpPipeline::fromDetectedText($this->value);
    }

    /**
     * @return array{
     *   word:string,
     *   definition:?string,
     *   synonyms:array<int,string>,
     *   definitionNeighbors:array<int,string>
     * }
     */
    public function semantics(?SemanticExplorer $explorer = null, ?NlpPipeline $pipeline = null): array
    {
        $word = trim(mb_strtolower($this->value));

        return ($explorer ?? $pipeline?->semanticExplorer() ?? new SemanticExplorer())->wordInsights($word);
    }
}
