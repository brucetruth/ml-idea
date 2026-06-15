<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Text;

use ML\IDEA\NLP\Contracts\NerTaggerInterface;
use ML\IDEA\NLP\Contracts\PosTaggerInterface;
use ML\IDEA\NLP\Contracts\TokenizerInterface;
use ML\IDEA\NLP\Contracts\TranslatorInterface;
use ML\IDEA\NLP\Detect\LanguagePipelineFactory;
use ML\IDEA\NLP\Lexicon\SemanticExplorer;
use ML\IDEA\NLP\Ner\RuleBasedNerTagger;
use ML\IDEA\NLP\Pos\RuleBasedPosTagger;
use ML\IDEA\NLP\Sentiment\SentimentAnalyzer;
use ML\IDEA\NLP\Tokenize\UnicodeWordTokenizer;

final class NlpPipeline
{
    public function __construct(
        private readonly TokenizerInterface $tokenizer = new UnicodeWordTokenizer(),
        private readonly PosTaggerInterface $posTagger = new RuleBasedPosTagger('en'),
        private readonly NerTaggerInterface $nerTagger = new RuleBasedNerTagger(),
        private readonly ?TranslatorInterface $translator = null,
        private readonly ?SentimentAnalyzer $sentimentAnalyzer = null,
        private readonly ?SemanticExplorer $semanticExplorer = null,
    ) {
    }

    public static function defaults(): self
    {
        return new self();
    }

    /** @param array<string, mixed> $pipeline */
    public static function fromLanguagePipeline(array $pipeline): self
    {
        return new self(
            tokenizer: $pipeline['tokenizer'],
            posTagger: $pipeline['posTagger'],
            nerTagger: $pipeline['nerTagger'],
            translator: $pipeline['translator'] ?? null,
            sentimentAnalyzer: $pipeline['sentiment'] ?? null,
        );
    }

    public static function forLanguage(string $language): self
    {
        return self::fromLanguagePipeline(LanguagePipelineFactory::forLanguage($language));
    }

    public static function fromDetectedText(string $text): self
    {
        return self::fromLanguagePipeline(LanguagePipelineFactory::fromDetectedText($text));
    }

    public function tokenizer(): TokenizerInterface
    {
        return $this->tokenizer;
    }

    public function posTagger(): PosTaggerInterface
    {
        return $this->posTagger;
    }

    public function nerTagger(): NerTaggerInterface
    {
        return $this->nerTagger;
    }

    public function translator(): ?TranslatorInterface
    {
        return $this->translator;
    }

    public function sentimentAnalyzer(): ?SentimentAnalyzer
    {
        return $this->sentimentAnalyzer;
    }

    public function semanticExplorer(): SemanticExplorer
    {
        return $this->semanticExplorer ?? new SemanticExplorer();
    }

    public function withTokenizer(TokenizerInterface $tokenizer): self
    {
        return new self($tokenizer, $this->posTagger, $this->nerTagger, $this->translator, $this->sentimentAnalyzer, $this->semanticExplorer);
    }

    public function withNerTagger(NerTaggerInterface $nerTagger): self
    {
        return new self($this->tokenizer, $this->posTagger, $nerTagger, $this->translator, $this->sentimentAnalyzer, $this->semanticExplorer);
    }
}
