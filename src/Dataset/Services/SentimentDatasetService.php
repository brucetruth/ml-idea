<?php

declare(strict_types=1);

namespace ML\IDEA\Dataset\Services;

use ML\IDEA\Dataset\Loaders\JsonDatasetLoader;

final class SentimentDatasetService
{
    /** @var array<int, array{id:int, text:string, label:string}>|null */
    private ?array $samples = null;

    public function __construct(private readonly ?string $datasetPath = null)
    {
    }

    /** @return array<int, array{id:int, text:string, label:string}> */
    public function samples(): array
    {
        if ($this->samples !== null) {
            return $this->samples;
        }

        $path = $this->datasetPath ?? dirname(__DIR__, 2) . '/Dataset/sentiment/sentiment_dataset.json';
        $rows = (new JsonDatasetLoader())->load($path);

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'id' => (int) ($row['id'] ?? count($out)),
                'text' => (string) ($row['text'] ?? ''),
                'label' => (string) ($row['label'] ?? 'negative'),
            ];
        }

        $this->samples = $out;
        return $out;
    }
}
