<?php

declare(strict_types=1);

namespace ML\IDEA\Tests;

use ML\IDEA\Vision\Analyzers\ColorPaletteAnalyzer;
use ML\IDEA\Vision\Analyzers\SkinToneHeuristicAnalyzer;
use ML\IDEA\Vision\ImageContentAnalyzer;
use ML\IDEA\Vision\ImageFeatureExtractor;
use PHPUnit\Framework\TestCase;

final class VisionAnalyzersTest extends TestCase
{
    public function testImageFeatureExtractorFromRgbMatrixSamplesCorrectly(): void
    {
        $matrix = [
            [[255, 0, 0], [255, 0, 0]],
            [[0, 0, 255], [0, 0, 255]],
        ];

        $samples = ImageFeatureExtractor::fromRgbMatrix($matrix, 3);
        self::assertNotEmpty($samples);
        self::assertLessThanOrEqual(4, count($samples));
    }

    public function testColorPaletteAnalyzerReturnsHexPalette(): void
    {
        $samples = [
            [250, 20, 20], [245, 25, 25], [240, 30, 30],
            [20, 20, 250], [25, 25, 245], [30, 30, 240],
        ];

        $analyzer = new ColorPaletteAnalyzer(k: 2, maxIterations: 60, batchSize: 6, seed: 42);
        $result = $analyzer->analyze($samples);

        self::assertArrayHasKey('palette', $result);
        self::assertNotEmpty($result['palette']);
        self::assertArrayHasKey('hex', $result['palette'][0]);
    }

    public function testSkinToneHeuristicAnalyzerDetectsHigherSkinRatio(): void
    {
        $samples = [
            [220, 170, 140], [210, 160, 130], [230, 180, 150],
            [40, 40, 220], [30, 30, 200],
        ];

        $analyzer = new SkinToneHeuristicAnalyzer(mediumThreshold: 0.3, highThreshold: 0.7);
        $result = $analyzer->analyze($samples);

        self::assertGreaterThan(0.3, $result['skin_ratio']);
        self::assertContains($result['risk_level'], ['medium', 'high']);
    }

    public function testImageContentAnalyzerCombinesPaletteAndSkinAnalysis(): void
    {
        $samples = [
            [220, 170, 140], [230, 180, 150], [210, 160, 130],
            [20, 30, 240], [30, 40, 220],
        ];

        $content = new ImageContentAnalyzer();
        $result = $content->analyze($samples);

        self::assertArrayHasKey('palette', $result);
        self::assertArrayHasKey('skin_analysis', $result);
    }
}
