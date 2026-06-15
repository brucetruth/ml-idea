<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\Vision\Classifiers\AuthenticityClassifier;
use ML\IDEA\Vision\Eval\VisionEval;

echo "Example 36 - VisionEval (ROC-AUC / PR-AUC on labeled authenticity fixtures)\n\n";

$fixture = __DIR__ . '/../tests/fixtures/vision_authenticity_signals.json';
[$signals, $labels] = VisionEval::loadSignalFixtures($fixture);

$classifier = new AuthenticityClassifier();
$classifier->train($signals, $labels);

$mlReport = VisionEval::classifierReport($classifier, $signals, $labels);
$heuristicReport = VisionEval::heuristicReport($signals, $labels);

echo 'ML classifier ROC-AUC: ' . round($mlReport['roc_auc'], 4) . PHP_EOL;
echo 'ML classifier PR-AUC: ' . round($mlReport['pr_auc'], 4) . PHP_EOL;
echo 'ML classifier accuracy: ' . round($mlReport['accuracy'], 4) . PHP_EOL;
echo 'Heuristic ROC-AUC: ' . round($heuristicReport['roc_auc'], 4) . PHP_EOL;

if (function_exists('imagecreatetruecolor')) {
    $gdDir = sys_get_temp_dir() . '/ml-idea-ex36-' . uniqid('', true);
    mkdir($gdDir, 0777, true);
    $aiPath = \ML\IDEA\Vision\Support\VisionTestImages::createFlatAiLike($gdDir . '/ai.png');
    $authPath = \ML\IDEA\Vision\Support\VisionTestImages::createTexturedAuthentic($gdDir . '/auth.png');
    $pathReport = VisionEval::classifierReportFromPaths($classifier, [$aiPath, $authPath], [1, 0]);
    echo 'GD image path ROC-AUC (2 samples): ' . round($pathReport['roc_auc'], 4) . PHP_EOL;
}

echo 'Confusion (ML): ' . json_encode($mlReport['confusion'], JSON_THROW_ON_ERROR) . PHP_EOL;
