<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Contracts;

use ML\IDEA\NLP\Doc\Doc;

/** spaCy-style pipeline step (tokenizer, tagger, ner, …). */
interface PipelineComponentInterface
{
    public function name(): string;

    public function process(Doc $doc, string $text): Doc;
}
