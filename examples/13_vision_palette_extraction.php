<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\Vision\Analyzers\ColorPaletteAnalyzer;
use ML\IDEA\Vision\ImageFeatureExtractor;

$imagePath = $argv[1] ?? (__DIR__ . '/artifacts/sample_photo.jpg');

if (!is_file($imagePath)) {
    echo "Image not found: {$imagePath}\n";
    echo "Pass an image path, e.g. php examples/13_vision_palette_extraction.php /path/to/photo.jpg\n";
    exit(1);
}

$samples = ImageFeatureExtractor::fromImageFile($imagePath, maxSamples: 4000);
$analyzer = new ColorPaletteAnalyzer(k: 5);
$result = $analyzer->analyze($samples);

echo "Example 13 - Vision Palette Extraction\n";
echo 'Image: ' . $imagePath . PHP_EOL;
echo 'Total sampled pixels: ' . $result['total_samples'] . PHP_EOL;

foreach ($result['palette'] as $i => $swatch) {
    echo sprintf(
        "#%d %s rgb(%d,%d,%d) %.2f%%\n",
        $i + 1,
        $swatch['hex'],
        $swatch['rgb'][0],
        $swatch['rgb'][1],
        $swatch['rgb'][2],
        $swatch['percentage'] * 100
    );
}
