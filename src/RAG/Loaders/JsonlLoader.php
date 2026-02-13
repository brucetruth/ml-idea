<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Loaders;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\RAG\Contracts\DocumentLoaderInterface;
use ML\IDEA\RAG\Document;

final class JsonlLoader implements DocumentLoaderInterface
{
    public function __construct(
        private readonly string $path,
        private readonly string $textField = 'text',
        private readonly string $idField = 'id',
    ) {
        if (!is_file($this->path)) {
            throw new InvalidArgumentException(sprintf('JSONL file not found: %s', $this->path));
        }
    }

    public function load(): array
    {
        $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $docs = [];
        foreach ($lines as $i => $line) {
            /** @var array<string, mixed> $row */
            $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            $text = isset($row[$this->textField]) ? (string) $row[$this->textField] : '';
            if (trim($text) === '') {
                continue;
            }

            $id = isset($row[$this->idField]) ? (string) $row[$this->idField] : ('jsonl-' . $i);
            unset($row[$this->textField]);

            $docs[] = new Document($id, $text, $row);
        }

        return $docs;
    }
}
