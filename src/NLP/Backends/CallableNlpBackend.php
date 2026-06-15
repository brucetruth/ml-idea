<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Backends;

use ML\IDEA\NLP\Contracts\NlpModelBackendInterface;
use ML\IDEA\NLP\Doc\Doc;

/** Wrap a callable for HF/Ollama/custom neural backends (HF pipeline-style hook). */
final class CallableNlpBackend implements NlpModelBackendInterface
{
    /** @param callable(string, Doc): Doc $processor */
    public function __construct(private $processor)
    {
    }

    public function process(string $text, Doc $draft): Doc
    {
        return ($this->processor)($text, $draft);
    }
}
