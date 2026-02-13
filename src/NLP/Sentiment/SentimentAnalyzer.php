<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Sentiment;

use ML\IDEA\Classifiers\LogisticRegression;
use ML\IDEA\Dataset\Services\SentimentDatasetService;
use ML\IDEA\NLP\TfidfVectorizer;

final class SentimentAnalyzer
{
    private TfidfVectorizer $vectorizer;
    private LogisticRegression $classifier;

    public function __construct(?TfidfVectorizer $vectorizer = null, ?LogisticRegression $classifier = null)
    {
        $this->vectorizer = $vectorizer ?? new TfidfVectorizer();
        $this->classifier = $classifier ?? new LogisticRegression(learningRate: 0.1, iterations: 300, l2Penalty: 0.001);
    }

    /** @param array<int, string> $texts @param array<int, string> $labels */
    public function train(array $texts, array $labels): void
    {
        $x = $this->vectorizer->fitTransform($texts);
        $y = array_map(static fn (string $label): int => mb_strtolower($label) === 'positive' ? 1 : 0, $labels);
        $this->classifier->train($x, $y);
    }

    public function trainFromBundledDataset(int $maxSamples = 3000): void
    {
        $samples = (new SentimentDatasetService())->samples();
        if ($maxSamples > 0 && count($samples) > $maxSamples) {
            $samples = array_slice($samples, 0, $maxSamples);
        }

        $texts = array_map(static fn (array $row): string => (string) $row['text'], $samples);
        $labels = array_map(static fn (array $row): string => (string) $row['label'], $samples);
        $this->train($texts, $labels);
    }

    public function predict(string $text): string
    {
        $m = $this->vectorizer->transform([$text]);
        $pred = $this->classifier->predict($m[0]);
        return ((int) $pred) === 1 ? 'positive' : 'negative';
    }

    /** @return array{negative:float, positive:float} */
    public function predictProba(string $text): array
    {
        $m = $this->vectorizer->transform([$text]);
        $proba = $this->classifier->predictProba($m[0]);
        return [
            'negative' => (float) ($proba[0] ?? 0.0),
            'positive' => (float) ($proba[1] ?? 0.0),
        ];
    }
}
