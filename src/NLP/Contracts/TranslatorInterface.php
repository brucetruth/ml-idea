<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Contracts;

interface TranslatorInterface
{
    public function translate(string $text, ?string $sourceLang = null, ?string $targetLang = null): string;
}
