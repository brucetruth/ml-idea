<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Text;

final readonly class Token
{
    public function __construct(
        public string $text,
        public int $start,
        public int $end,
        public string $norm,
        public string $type = 'word',
    ) {
    }

    /**
     * @return array{text: string, start: int, end: int, norm: string, type: string}
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'start' => $this->start,
            'end' => $this->end,
            'norm' => $this->norm,
            'type' => $this->type,
        ];
    }
}
