<?php

declare(strict_types=1);

namespace ML\IDEA\NLP\Eval;

use ML\IDEA\Metrics\ClassificationReport;
use ML\IDEA\NLP\Ner\Entity;
use ML\IDEA\NLP\Sentiment\SentimentAnalyzer;

final class NlpEval
{
    /**
     * Token-level accuracy for aligned label sequences (POS, IOB NER tags, etc.).
     *
     * @param array<int, string> $truth
     * @param array<int, string> $predictions
     */
    public static function tokenAccuracy(array $truth, array $predictions): float
    {
        if ($truth === [] || count($truth) !== count($predictions)) {
            return 0.0;
        }

        $correct = 0;
        foreach ($truth as $i => $label) {
            if ($label === $predictions[$i]) {
                $correct++;
            }
        }

        return $correct / count($truth);
    }

    /**
     * Per-label precision/recall/F1 for flattened token labels.
     *
     * @param array<int, array<int, string>> $truthSequences
     * @param array<int, array<int, string>> $predictionSequences
     * @return array<string, array{precision: float, recall: float, f1: float, support: int}>
     */
    public static function tokenLabelReport(array $truthSequences, array $predictionSequences): array
    {
        return ClassificationReport::generate(
            self::flattenSequences($truthSequences),
            self::flattenSequences($predictionSequences),
        );
    }

    /**
     * Macro-averaged F1 across labels present in the flattened token report.
     *
     * @param array<int, array<int, string>> $truthSequences
     * @param array<int, array<int, string>> $predictionSequences
     */
    public static function tokenMacroF1(array $truthSequences, array $predictionSequences): float
    {
        $report = self::tokenLabelReport($truthSequences, $predictionSequences);
        if ($report === []) {
            return 0.0;
        }

        $sum = 0.0;
        foreach ($report as $row) {
            $sum += (float) ($row['f1'] ?? 0.0);
        }

        return $sum / count($report);
    }

    /**
     * Sentiment evaluation on a labeled text fixture.
     *
     * @param array<int, string> $texts
     * @param array<int, string> $truthLabels
     * @return array{
     *     accuracy: float,
     *     report: array<string, array{precision: float, recall: float, f1: float, support: int}>,
     *     confusion: array<string, array<string, int>>
     * }
     */
    public static function sentimentReport(array $texts, array $truthLabels, SentimentAnalyzer $analyzer): array
    {
        $predictions = array_map(static fn (string $text): string => $analyzer->predict($text), $texts);
        $truth = array_map(
            static fn (string $label): string => match (mb_strtolower(trim($label))) {
                'positive', 'pos' => 'positive',
                'negative', 'neg' => 'negative',
                default => 'neutral',
            },
            $truthLabels,
        );

        $correct = 0;
        foreach ($truth as $i => $label) {
            if ($label === $predictions[$i]) {
                $correct++;
            }
        }

        return [
            'accuracy' => $texts === [] ? 0.0 : $correct / count($texts),
            'report' => ClassificationReport::generate($truth, $predictions),
            'confusion' => ClassificationReport::confusionMatrix($truth, $predictions),
        ];
    }

    /**
     * Strict entity-span precision/recall/F1 (exact text + label match).
     *
     * @param array<int, Entity> $truth
     * @param array<int, Entity> $predictions
     * @return array{precision: float, recall: float, f1: float, support: int}
     */
    public static function entitySpanScores(array $truth, array $predictions): array
    {
        $truthKeys = array_map([self::class, 'entityKey'], $truth);
        $predKeys = array_map([self::class, 'entityKey'], $predictions);

        $truthSet = array_fill_keys($truthKeys, true);
        $predSet = array_fill_keys($predKeys, true);

        $tp = 0;
        foreach ($predKeys as $key) {
            if (isset($truthSet[$key])) {
                $tp++;
            }
        }

        $fp = count($predKeys) - $tp;
        $fn = count($truthKeys) - $tp;
        $precision = $tp + $fp === 0 ? 0.0 : $tp / ($tp + $fp);
        $recall = $tp + $fn === 0 ? 0.0 : $tp / ($tp + $fn);
        $f1 = $precision + $recall === 0.0 ? 0.0 : (2 * $precision * $recall) / ($precision + $recall);

        return [
            'precision' => $precision,
            'recall' => $recall,
            'f1' => $f1,
            'support' => count($truthKeys),
        ];
    }

    /** @param array<int, array<int, string>> $sequences @return array<int, string> */
    private static function flattenSequences(array $sequences): array
    {
        $flat = [];
        foreach ($sequences as $sequence) {
            foreach ($sequence as $label) {
                $flat[] = $label;
            }
        }

        return $flat;
    }

    private static function entityKey(Entity $entity): string
    {
        return mb_strtolower(trim($entity->label)) . '|' . mb_strtolower(trim($entity->text));
    }
}
