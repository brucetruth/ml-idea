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

    public function __construct(
        ?TfidfVectorizer $vectorizer = null,
        ?LogisticRegression $classifier = null,
    ) {
        $this->vectorizer = $vectorizer ?? new TfidfVectorizer(removeStopwords: true);
        $this->classifier = $classifier ?? new LogisticRegression(learningRate: 0.1, iterations: 300, l2Penalty: 0.001);
    }

    /** @param array<int, string> $texts @param array<int, string> $labels */
    public function train(array $texts, array $labels): void
    {
        $x = $this->vectorizer->fitTransform($texts);
        $y = array_map([self::class, 'normalizeLabel'], $labels);
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
        $proba = $this->predictProba($text);
        $bestLabel = 'neutral';
        $bestScore = -INF;

        foreach ($proba as $label => $score) {
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestLabel = $label;
            }
        }

        return $bestLabel;
    }

    /** @return array{negative:float, positive:float, neutral:float} */
    public function predictProba(string $text): array
    {
        $m = $this->vectorizer->transform([$text]);
        $proba = $this->classifier->predictProba($m[0]);

        $negative = (float) ($proba['negative'] ?? 0.0);
        $neutral = (float) ($proba['neutral'] ?? 0.0);
        $positive = (float) ($proba['positive'] ?? 0.0);

        if ($neutral <= 0.0 && ($negative > 0.0 || $positive > 0.0)) {
            // Backward compatibility for binary-only models trained before neutral support.
            $neutral = max(0.0, 1.0 - abs($positive - $negative));
            $scale = $positive + $negative;
            if ($scale > 0.0) {
                $remaining = max(0.0, 1.0 - $neutral);
                $negative = ($negative / $scale) * $remaining;
                $positive = ($positive / $scale) * $remaining;
            }
        }

        return [
            'negative' => $negative,
            'positive' => $positive,
            'neutral' => min(1.0, $neutral),
        ];
    }

    private static function normalizeLabel(string $label): string
    {
        return match (mb_strtolower(trim($label))) {
            'positive', 'pos', '1' => 'positive',
            'negative', 'neg', '0' => 'negative',
            'neutral', 'neu' => 'neutral',
            default => 'neutral',
        };
    }
}
