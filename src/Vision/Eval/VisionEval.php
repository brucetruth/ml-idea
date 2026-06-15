<?php

declare(strict_types=1);

namespace ML\IDEA\Vision\Eval;

use ML\IDEA\Metrics\ClassificationMetrics;
use ML\IDEA\Metrics\ClassificationReport;
use ML\IDEA\Vision\Analyzers\ImageAuthenticityAnalyzer;
use ML\IDEA\Vision\Classifiers\AuthenticityClassifier;
use ML\IDEA\Vision\Features\ImageForensicsFeatureExtractor;

final class VisionEval
{
    /**
     * Binary authenticity evaluation with ROC-AUC / PR-AUC (1 = AI-generated).
     *
     * @param array<int, array<string, float|int|bool|string>> $signalSets
     * @param array<int, int|float|string|bool> $labels
     * @param callable(array<string, float|int|bool|string>): float $scoreFn returns P(AI)
     * @return array{
     *     roc_auc: float,
     *     pr_auc: float,
     *     accuracy: float,
     *     report: array<string, array{precision: float, recall: float, f1: float, support: int}>,
     *     confusion: array<string, array<string, int>>
     * }
     */
    public static function authenticityReport(
        array $signalSets,
        array $labels,
        callable $scoreFn,
        int|float|string|bool $positiveClass = 1,
        int|float|string|bool $negativeClass = 0,
        float $threshold = 0.5,
    ): array {
        $scores = array_map(static fn (array $signals): float => (float) $scoreFn($signals), $signalSets);
        $predictions = array_map(
            static fn (float $score): int|float|string|bool => $score >= $threshold ? $positiveClass : $negativeClass,
            $scores,
        );

        return [
            'roc_auc' => ClassificationMetrics::rocAuc($labels, $scores, $positiveClass),
            'pr_auc' => ClassificationMetrics::prAuc($labels, $scores, $positiveClass),
            'accuracy' => ClassificationMetrics::accuracy($labels, $predictions),
            'report' => ClassificationReport::generate($labels, $predictions),
            'confusion' => ClassificationReport::confusionMatrix($labels, $predictions),
        ];
    }

    /**
     * Evaluate a trained AuthenticityClassifier on labeled signal sets.
     *
     * @param array<int, array<string, float|int|bool|string>> $signalSets
     * @param array<int, int|float|string|bool> $labels
     * @return array{roc_auc: float, pr_auc: float, accuracy: float, report: array<string, array{precision: float, recall: float, f1: float, support: int}>, confusion: array<string, array<string, int>>}
     */
    public static function classifierReport(AuthenticityClassifier $classifier, array $signalSets, array $labels): array
    {
        return self::authenticityReport(
            $signalSets,
            $labels,
            static fn (array $signals): float => $classifier->predictSignals($signals)['ai_probability'],
        );
    }

    /**
     * Evaluate the heuristic ImageAuthenticityAnalyzer scorer.
     *
     * @param array<int, array<string, float|int|bool|string>> $signalSets
     * @param array<int, int|float|string|bool> $labels
     * @return array{roc_auc: float, pr_auc: float, accuracy: float, report: array<string, array{precision: float, recall: float, f1: float, support: int}>, confusion: array<string, array<string, int>>}
     */
    public static function heuristicReport(array $signalSets, array $labels, ?ImageAuthenticityAnalyzer $analyzer = null): array
    {
        $analyzer ??= new ImageAuthenticityAnalyzer();

        return self::authenticityReport(
            $signalSets,
            $labels,
            static fn (array $signals): float => $analyzer->score($signals)['score'],
        );
    }

    /**
     * Load labeled signal fixtures from JSON.
     *
     * @return array{0: array<int, array<string, float|int|bool|string>>, 1: array<int, int|float|string|bool>}
     */
    public static function loadSignalFixtures(string $path, ?ImageForensicsFeatureExtractor $extractor = null): array
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \InvalidArgumentException(sprintf('Fixture not found: %s', $path));
        }

        /** @var array<int, array{name?: string, path?: string, signals?: array<string, mixed>, label: int|float|string|bool}> $rows */
        $rows = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $extractor ??= new ImageForensicsFeatureExtractor();

        $signalSets = [];
        $labels = [];
        foreach ($rows as $row) {
            if (isset($row['path']) && is_string($row['path']) && is_file($row['path'])) {
                $signalSets[] = $extractor->fromImageFile($row['path']);
            } elseif (isset($row['signals']) && is_array($row['signals'])) {
                $signalSets[] = $row['signals'];
            } else {
                continue;
            }
            $labels[] = $row['label'];
        }

        return [$signalSets, $labels];
    }

    /**
     * Evaluate classifier on labeled image paths (runs full forensics pipeline per file).
     *
     * @param array<int, string> $paths
     * @param array<int, int|float|string|bool> $labels
     * @return array{roc_auc: float, pr_auc: float, accuracy: float, report: array<string, array{precision: float, recall: float, f1: float, support: int}>, confusion: array<string, array<string, int>>}
     */
    public static function classifierReportFromPaths(
        AuthenticityClassifier $classifier,
        array $paths,
        array $labels,
        ?ImageForensicsFeatureExtractor $extractor = null,
    ): array {
        $extractor ??= new ImageForensicsFeatureExtractor();
        $signalSets = array_map(static fn (string $path): array => $extractor->fromImageFile($path), $paths);

        return self::classifierReport($classifier, $signalSets, $labels);
    }
}
