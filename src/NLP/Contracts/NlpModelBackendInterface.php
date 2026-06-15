<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Contracts;

use ML\IDEA\NLP\Doc\Doc;

/**
 * Optional backend hook for remote or neural models (HF API, ONNX, etc.).
 * Local rule/ML pipelines use {@see \ML\IDEA\NLP\Language} without a backend.
 */
interface NlpModelBackendInterface
{
    public function process(string $text, Doc $draft): Doc;
}
