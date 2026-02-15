<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Translate;

use ML\IDEA\Dataset\Services\DictionaryDatasetService;
use ML\IDEA\NLP\Contracts\TranslatorInterface;
use ML\IDEA\RAG\Contracts\LlmClientInterface;

final class EnglishBembaTranslator implements TranslatorInterface
{
    private HybridTranslator $pipeline;
    private DictionaryTranslator $wordTranslator;
    private ?LlmClientInterface $llmHelper = null;

    /** @param array<string, array<int, string>>|null $map */
    public function __construct(?array $map = null, ?DictionaryDatasetService $dictionary = null)
    {
        if ($map !== null) {
            $word = [];
            $phrase = [];
            foreach ($map as $k => $vals) {
                $v = trim((string) ($vals[0] ?? ''));
                if ($v === '') {
                    continue;
                }
                if (str_contains($k, ' ')) {
                    $phrase[$k] = $v;
                } else {
                    $word[$k] = $v;
                }
            }
        } else {
            $dictionary ??= new DictionaryDatasetService();
            $word = $dictionary->englishToBembaWordMap();
            $phrase = $dictionary->englishToBembaPhraseMap(2, 5);
        }

        $this->wordTranslator = new DictionaryTranslator($word);
        $this->pipeline = new HybridTranslator(
            new PhraseTableTranslator($phrase, 2, 5),
            $this->wordTranslator
        );
    }

    public function translateWord(string $english): string
    {
        return $this->wordTranslator->translateWord($english);
    }

    public function withLLM(LlmClientInterface $llm): self
    {
        $clone = clone $this;
        $clone->llmHelper = $llm;
        return $clone;
    }

    public function translate(string $text, ?string $sourceLang = 'en', ?string $targetLang = 'bem'): string
    {
        $draft = $this->pipeline->translate($text, $sourceLang, $targetLang);

        if ($this->llmHelper === null || trim($text) === '') {
            return $draft;
        }

        try {
            $prompt = "You are improving an English->Bemba translation.\n"
                . "Rules:\n"
                . "- Keep the meaning of the English source exactly.\n"
                . "- Only correct the final Bemba output text.\n"
                . "- Do not add explanations, notes, labels, or quotes.\n"
                . "- Return only the corrected Bemba text.\n\n"
                . "English source:\n" . $text . "\n\n"
                . "Draft Bemba translation:\n" . $draft . "\n\n"
                . "Corrected Bemba translation:";

            $refined = trim($this->llmHelper->generate($prompt, ['temperature' => 0.1, 'max_tokens' => 180]));
            if ($refined === '') {
                return $draft;
            }

            // Keep only the first line to enforce "final output only" behavior.
            $line = trim((string) preg_split('/\R/u', $refined, 2)[0]);
            return $line !== '' ? $line : $draft;
        } catch (\Throwable) {
            return $draft;
        }
    }
}
