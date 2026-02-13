<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\Vision\Analyzers\ImageAuthenticityAnalyzer;

$imagePath = $argv[1] ?? (__DIR__ . '/artifacts/sample_photo.jpg');

if (!is_file($imagePath)) {
    echo "Image not found: {$imagePath}\n";
    echo "Pass an image path, e.g. php examples/15_vision_authenticity_risk_demo.php /path/to/photo.jpg\n";
    exit(1);
}

$analyzer = new ImageAuthenticityAnalyzer();
$result = $analyzer->analyzeFile($imagePath, maxSamples: 5000);

echo "Example 15 - Vision Authenticity Risk Demo\n";
echo 'Image: ' . $imagePath . PHP_EOL;
echo 'AI generation risk: ' . $result['ai_generation_risk'] . PHP_EOL;
echo 'Score: ' . round($result['score'], 3) . PHP_EOL;
echo 'Confidence: ' . round($result['confidence'], 3) . PHP_EOL;

if ($result['notes'] !== []) {
    echo "Signals:\n";
    foreach ($result['notes'] as $note) {
        echo '- ' . $note . PHP_EOL;
    }
}

echo "\nDisclaimer: this is a heuristic authenticity risk estimate, not a definitive detector.\n";
