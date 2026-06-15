<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use ML\IDEA\Vision\Classifiers\AuthenticityClassifier;

echo "Example 35 - Vision ML classifier (forensics features + LogisticRegression)\n\n";

$aiSignals = [
    [
        'generator_hint_score' => 1.0,
        'filename_hint_score' => 0.8,
        'has_exif_camera' => false,
        'flatness_score' => 0.75,
        'color_diversity' => 0.06,
        'clipping_ratio' => 0.35,
        'luma_std' => 12.0,
        'saturation_mean' => 0.4,
        'mean_r' => 128.0,
        'mean_g' => 120.0,
        'mean_b' => 115.0,
    ],
    [
        'generator_hint_score' => 0.9,
        'filename_hint_score' => 0.6,
        'has_exif_camera' => false,
        'flatness_score' => 0.7,
        'color_diversity' => 0.08,
        'clipping_ratio' => 0.3,
        'luma_std' => 10.0,
        'saturation_mean' => 0.35,
        'mean_r' => 130.0,
        'mean_g' => 125.0,
        'mean_b' => 118.0,
    ],
    [
        'generator_hint_score' => 0.85,
        'filename_hint_score' => 1.0,
        'has_exif_camera' => false,
        'flatness_score' => 0.65,
        'color_diversity' => 0.07,
        'clipping_ratio' => 0.28,
        'luma_std' => 11.0,
        'saturation_mean' => 0.38,
        'mean_r' => 125.0,
        'mean_g' => 122.0,
        'mean_b' => 119.0,
    ],
];

$authenticSignals = [
    [
        'generator_hint_score' => 0.0,
        'filename_hint_score' => 0.0,
        'has_exif_camera' => true,
        'flatness_score' => 0.2,
        'color_diversity' => 0.25,
        'clipping_ratio' => 0.08,
        'luma_std' => 38.0,
        'saturation_mean' => 0.55,
        'mean_r' => 90.0,
        'mean_g' => 110.0,
        'mean_b' => 80.0,
    ],
    [
        'generator_hint_score' => 0.0,
        'filename_hint_score' => 0.0,
        'has_exif_camera' => true,
        'flatness_score' => 0.18,
        'color_diversity' => 0.22,
        'clipping_ratio' => 0.1,
        'luma_std' => 42.0,
        'saturation_mean' => 0.5,
        'mean_r' => 95.0,
        'mean_g' => 105.0,
        'mean_b' => 85.0,
    ],
    [
        'generator_hint_score' => 0.05,
        'filename_hint_score' => 0.0,
        'has_exif_camera' => true,
        'flatness_score' => 0.22,
        'color_diversity' => 0.28,
        'clipping_ratio' => 0.09,
        'luma_std' => 36.0,
        'saturation_mean' => 0.52,
        'mean_r' => 88.0,
        'mean_g' => 112.0,
        'mean_b' => 78.0,
    ],
];

$trainSignals = array_merge($aiSignals, $authenticSignals);
$trainLabels = [1, 1, 1, 0, 0, 0];

$classifier = new AuthenticityClassifier();
$classifier->train($trainSignals, $trainLabels);

$probe = [
    'generator_hint_score' => 0.95,
    'filename_hint_score' => 0.7,
    'has_exif_camera' => false,
    'flatness_score' => 0.72,
    'color_diversity' => 0.07,
    'clipping_ratio' => 0.32,
    'luma_std' => 11.0,
    'saturation_mean' => 0.36,
    'mean_r' => 127.0,
    'mean_g' => 121.0,
    'mean_b' => 116.0,
];

$result = $classifier->predictSignals($probe);
echo 'AI probe label: ' . json_encode($result['label'], JSON_THROW_ON_ERROR) . PHP_EOL;
echo 'AI probability: ' . round($result['ai_probability'], 4) . PHP_EOL;

$roundTrip = AuthenticityClassifier::fromArray($classifier->toArray());
$restored = $roundTrip->predictSignals($probe);
echo 'Restored model AI probability: ' . round($restored['ai_probability'], 4) . PHP_EOL;
