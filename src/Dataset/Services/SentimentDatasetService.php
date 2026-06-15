<?php

declare(strict_types=1);

namespace ML\IDEA\Dataset\Services;

use ML\IDEA\Dataset\Loaders\JsonDatasetLoader;
use ML\IDEA\Dataset\Registry\DatasetPaths;

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

        $path = $this->datasetPath ?? DatasetPaths::resolve('sentiment/sentiment_dataset.json');
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

        $out = $this->ensureNeutralSamples($out);

        $this->samples = $out;
        return $out;
    }

    /** @param array<int, array{id:int, text:string, label:string}> $samples
     * @return array<int, array{id:int, text:string, label:string}>
     */
    private function ensureNeutralSamples(array $samples): array
    {
        foreach ($samples as $row) {
            if (($row['label'] ?? '') === 'neutral') {
                return $samples;
            }
        }

        $seedPath = DatasetPaths::seed('sentiment/sentiment_dataset.json');
        if (!is_file($seedPath)) {
            return array_merge($samples, self::builtinNeutralSamples());
        }

        $seedRows = (new JsonDatasetLoader())->load($seedPath);
        $neutral = [];
        foreach ($seedRows as $row) {
            if (!is_array($row) || ($row['label'] ?? '') !== 'neutral') {
                continue;
            }
            $neutral[] = [
                'id' => (int) ($row['id'] ?? count($samples) + count($neutral)),
                'text' => (string) ($row['text'] ?? ''),
                'label' => 'neutral',
            ];
        }

        return array_merge($samples, $neutral, self::builtinNeutralSamples());
    }

    /** @return array<int, array{id:int, text:string, label:string}> */
    private static function builtinNeutralSamples(): array
    {
        $phrases = [
            'okay average nothing special',
            'fine I guess neither good nor bad',
            'the package arrived on schedule',
            'it works as described nothing more',
            'standard unremarkable experience overall',
            'neither impressed nor disappointed really',
            'acceptable quality for the price point',
            'just an ordinary regular update release',
        ];

        $rows = [];
        foreach ($phrases as $i => $text) {
            $rows[] = ['id' => 9000 + $i, 'text' => $text, 'label' => 'neutral'];
        }

        return $rows;
    }
}
