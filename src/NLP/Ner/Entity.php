<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Ner;

final readonly class Entity
{
    public function __construct(
        public string $text,
        public string $label,
        public int $start,
        public int $end,
        public float $confidence = 0.7,
    ) {
    }

    /** @return array{text:string,label:string,start:int,end:int,confidence:float} */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'label' => $this->label,
            'start' => $this->start,
            'end' => $this->end,
            'confidence' => $this->confidence,
        ];
    }
}
