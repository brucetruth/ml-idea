<?php

declare(strict_types=1);

namespace ML\IDEA\RAG\Loaders;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\RAG\Contracts\DocumentLoaderInterface;
use ML\IDEA\RAG\Document;

final class PdoLoader implements DocumentLoaderInterface
{
    /**
     * @param array<string, scalar|null> $params
     * @param array<int, string>|null $metadataFields
     */
    public function __construct(
        private readonly \PDO $pdo,
        private readonly string $query,
        private readonly string $textField = 'text',
        private readonly string $idField = 'id',
        private readonly array $params = [],
        private readonly ?array $metadataFields = null,
    ) {
        if (trim($this->query) === '') {
            throw new InvalidArgumentException('query cannot be empty.');
        }
    }

    public function load(): array
    {
        $stmt = $this->pdo->prepare($this->query);
        if ($stmt === false) {
            throw new InvalidArgumentException('Failed to prepare query in PdoLoader.');
        }

        if (!$stmt->execute($this->params)) {
            throw new InvalidArgumentException('Failed to execute query in PdoLoader.');
        }

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        $docs = [];
        foreach ($rows as $i => $row) {
            if (!is_array($row)) {
                continue;
            }

            $text = isset($row[$this->textField]) ? (string) $row[$this->textField] : '';
            if (trim($text) === '') {
                continue;
            }

            $id = isset($row[$this->idField]) ? (string) $row[$this->idField] : ('db-' . $i);
            $metadata = $this->extractMetadata($row);

            $docs[] = new Document($id, $text, $metadata);
        }

        return $docs;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function extractMetadata(array $row): array
    {
        if ($this->metadataFields !== null) {
            $meta = [];
            foreach ($this->metadataFields as $field) {
                if (array_key_exists($field, $row)) {
                    $meta[$field] = $row[$field];
                }
            }

            return $meta;
        }

        unset($row[$this->idField], $row[$this->textField]);
        return $row;
    }
}
