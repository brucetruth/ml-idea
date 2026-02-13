<?php

declare(strict_types=1);

namespace ML\IDEA\Vision\Analyzers;

use ML\IDEA\Vision\Features\ImageForensicsFeatureExtractor;

final class ImageAuthenticityAnalyzer
{
    public function __construct(
        private readonly ImageForensicsFeatureExtractor $features = new ImageForensicsFeatureExtractor(),
    ) {
    }

    /**
     * @return array{ai_generation_risk: string, score: float, confidence: float, signals: array<string, float|int|bool|string>, notes: array<int, string>}
     */
    public function analyzeFile(string $path, int $maxSamples = 5000): array
    {
        $signals = $this->features->fromImageFile($path, $maxSamples);
        return $this->score($signals);
    }

    /**
     * @param array<string, float|int|bool|string> $signals
     * @return array{ai_generation_risk: string, score: float, confidence: float, signals: array<string, float|int|bool|string>, notes: array<int, string>}
     */
    public function score(array $signals): array
    {
        $score = 0.0;
        $notes = [];

        $generatorHint = (float) ($signals['generator_hint_score'] ?? 0.0);
        if ($generatorHint > 0.0) {
            $score += min(0.45, $generatorHint * 0.45);
            $notes[] = 'Generator-related metadata hints detected.';
        }

        $filenameHint = (float) ($signals['filename_hint_score'] ?? 0.0);
        if ($filenameHint > 0.0) {
            $score += min(0.20, $filenameHint * 0.20);
            $notes[] = 'Filename contains generator-like keywords.';
        }

        $hasCamera = (bool) ($signals['has_exif_camera'] ?? false);
        if (!$hasCamera) {
            $score += 0.12;
            $notes[] = 'No camera EXIF fields found (weak signal).';
        }

        $flatness = (float) ($signals['flatness_score'] ?? 0.0);
        if ($flatness > 0.58) {
            $score += 0.12;
            $notes[] = 'Unusually smooth tonal distribution.';
        }

        $diversity = (float) ($signals['color_diversity'] ?? 1.0);
        if ($diversity < 0.08) {
            $score += 0.10;
            $notes[] = 'Low coarse color diversity.';
        }

        $clipping = (float) ($signals['clipping_ratio'] ?? 0.0);
        if ($clipping > 0.35) {
            $score += 0.10;
            $notes[] = 'High clipping ratio.';
        }

        $score = max(0.0, min(1.0, $score));

        $risk = 'low';
        if ($score >= 0.72) {
            $risk = 'high';
        } elseif ($score >= 0.45) {
            $risk = 'medium';
        }

        $confidence = 0.45 + (0.5 * min(1.0, abs($score - 0.5) * 2.0));

        return [
            'ai_generation_risk' => $risk,
            'score' => $score,
            'confidence' => $confidence,
            'signals' => $signals,
            'notes' => $notes,
        ];
    }
}
