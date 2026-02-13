<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Vision\Analyzers\ImageAuthenticityAnalyzer;
use PHPUnit\Framework\TestCase;

final class VisionAuthenticityAnalyzerTest extends TestCase
{
    public function testAuthenticityAnalyzerScoresHighWhenGeneratorSignalsAreStrong(): void
    {
        $analyzer = new ImageAuthenticityAnalyzer();
        $result = $analyzer->score([
            'generator_hint_score' => 1.0,
            'filename_hint_score' => 1.0,
            'has_exif_camera' => false,
            'flatness_score' => 0.8,
            'color_diversity' => 0.05,
            'clipping_ratio' => 0.4,
        ]);

        self::assertSame('high', $result['ai_generation_risk']);
        self::assertGreaterThan(0.72, $result['score']);
    }

    public function testAuthenticityAnalyzerScoresLowerWithCameraLikeSignals(): void
    {
        $analyzer = new ImageAuthenticityAnalyzer();
        $result = $analyzer->score([
            'generator_hint_score' => 0.0,
            'filename_hint_score' => 0.0,
            'has_exif_camera' => true,
            'flatness_score' => 0.2,
            'color_diversity' => 0.25,
            'clipping_ratio' => 0.1,
        ]);

        self::assertSame('low', $result['ai_generation_risk']);
        self::assertLessThan(0.45, $result['score']);
    }
}
