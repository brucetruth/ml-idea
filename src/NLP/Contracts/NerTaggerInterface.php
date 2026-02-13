<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Contracts;

use ML\IDEA\NLP\Ner\Entity;

interface NerTaggerInterface
{
    /**
     * @return array<int, Entity>
     */
    public function extract(string $text): array;
}
