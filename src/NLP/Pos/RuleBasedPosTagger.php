<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Pos;

use ML\IDEA\NLP\Contracts\PosTaggerInterface;
use ML\IDEA\NLP\Text\Token;

final class RuleBasedPosTagger implements PosTaggerInterface
{
    /** @var array<string, string> */
    private array $lexicon;

    public function __construct(private string $language = 'en', array $customLexicon = [])
    {
        $this->lexicon = array_merge(self::baseLexicon($language), $customLexicon);
    }

    /** @param array<string, string> $lexicon */
    public function withLexicon(array $lexicon): self
    {
        $clone = clone $this;
        $clone->lexicon = array_merge($clone->lexicon, $lexicon);
        return $clone;
    }

    /**
     * @param array<int, Token> $tokens
     * @return array<int, array{token: Token, pos: string}>
     */
    public function tag(array $tokens): array
    {
        $out = [];
        foreach ($tokens as $token) {
            $out[] = ['token' => $token, 'pos' => $this->guessPos($token->norm, $token->text)];
        }
        return $out;
    }

    private function guessPos(string $norm, string $raw): string
    {
        if (isset($this->lexicon[$norm])) {
            return $this->lexicon[$norm];
        }
        if (preg_match('/^\d+([.,]\d+)?$/', $norm) === 1) {
            return 'NUM';
        }
        if (preg_match('/^[[:punct:]]+$/', $raw) === 1) {
            return 'PUNCT';
        }
        if ($this->language === 'en' && preg_match('/ing$|ed$/', $norm) === 1) {
            return 'VERB';
        }
        if (($this->language === 'en' && preg_match('/ly$/', $norm) === 1)
            || ($this->language === 'fr' && preg_match('/ment$/', $norm) === 1)
            || ($this->language === 'es' && preg_match('/mente$/', $norm) === 1)) {
            return 'ADV';
        }
        if (($this->language === 'en' && preg_match('/ous$|ive$|al$|ful$|less$/', $norm) === 1)
            || ($this->language === 'fr' && preg_match('/eux$|able$|if$|ive$/', $norm) === 1)
            || ($this->language === 'es' && preg_match('/oso$|osa$|able$|ible$/', $norm) === 1)) {
            return 'ADJ';
        }
        if (preg_match('/^[A-Z][\p{L}\-]+$/u', $raw) === 1) {
            return 'PROPN';
        }
        return 'NOUN';
    }

    /** @return array<string, string> */
    private static function baseLexicon(string $language): array
    {
        return match ($language) {
            'fr' => [
                'le' => 'DET', 'la' => 'DET', 'les' => 'DET', 'un' => 'DET', 'une' => 'DET',
                'et' => 'CONJ', 'ou' => 'CONJ', 'mais' => 'CONJ',
                'je' => 'PRON', 'tu' => 'PRON', 'il' => 'PRON', 'elle' => 'PRON', 'nous' => 'PRON', 'vous' => 'PRON',
                'est' => 'VERB', 'sont' => 'VERB', 'etre' => 'VERB',
            ],
            'es' => [
                'el' => 'DET', 'la' => 'DET', 'los' => 'DET', 'las' => 'DET', 'un' => 'DET', 'una' => 'DET',
                'y' => 'CONJ', 'o' => 'CONJ', 'pero' => 'CONJ',
                'yo' => 'PRON', 'tu' => 'PRON', 'ella' => 'PRON', 'nosotros' => 'PRON',
                'es' => 'VERB', 'son' => 'VERB', 'ser' => 'VERB',
            ],
            default => [
                'the' => 'DET', 'a' => 'DET', 'an' => 'DET',
                'and' => 'CONJ', 'or' => 'CONJ', 'but' => 'CONJ',
                'in' => 'ADP', 'on' => 'ADP', 'at' => 'ADP', 'to' => 'PRT',
                'i' => 'PRON', 'you' => 'PRON', 'he' => 'PRON', 'she' => 'PRON', 'we' => 'PRON', 'they' => 'PRON',
                'is' => 'VERB', 'are' => 'VERB', 'was' => 'VERB', 'were' => 'VERB', 'be' => 'VERB',
            ],
        };
    }
}
