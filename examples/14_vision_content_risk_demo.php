<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\Vision\ImageContentAnalyzer;
use ML\IDEA\Vision\ImageFeatureExtractor;

$imagePath = $argv[1] ?? (__DIR__ . '/artifacts/sample_photo.jpg');

if (!is_file($imagePath)) {
    echo "Image not found: {$imagePath}\n";
    echo "Pass an image path, e.g. php examples/14_vision_content_risk_demo.php /path/to/photo.jpg\n";
    exit(1);
}

$samples = ImageFeatureExtractor::fromImageFile($imagePath, maxSamples: 5000);
$analyzer = new ImageContentAnalyzer();
$result = $analyzer->analyze($samples);

echo "Example 14 - Vision Content Risk Demo\n";
echo 'Image: ' . $imagePath . PHP_EOL;
echo 'Skin ratio: ' . round($result['skin_analysis']['skin_ratio'] * 100, 2) . "%\n";
echo 'Risk level: ' . $result['skin_analysis']['risk_level'] . PHP_EOL;

echo "Top palette colors:\n";
foreach (array_slice($result['palette'], 0, 3) as $swatch) {
    echo sprintf("- %s (%.2f%%)\n", $swatch['hex'], $swatch['percentage'] * 100);
}

echo "\nDisclaimer: this is a simple heuristic and not a definitive nudity detector.\n";
