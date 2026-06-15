<?php

declare(strict_types=1);

namespace ML\IDEA\Vision\Features;

/** Fixed-order numeric vector from forensics feature maps. */
final class ImageFeatureVectorizer
{
    /** @var array<int, string> */
    private const FEATURES = [
        'generator_hint_score',
        'filename_hint_score',
        'has_exif_camera',
        'flatness_score',
        'color_diversity',
        'clipping_ratio',
        'luma_std',
        'saturation_mean',
        'mean_r',
        'mean_g',
        'mean_b',
        'dct_high_freq_ratio',
        'noise_residual_std',
        'patch_variance_std',
    ];

    /**
     * @param array<string, float|int|bool|string> $signals
     * @return array<int, float>
     */
    public function transform(array $signals): array
    {
        $vector = [];
        foreach (self::FEATURES as $key) {
            $value = $signals[$key] ?? 0.0;
            if (is_bool($value)) {
                $vector[] = $value ? 1.0 : 0.0;
            } elseif ($key === 'mean_r' || $key === 'mean_g' || $key === 'mean_b') {
                $vector[] = (float) $value / 255.0;
            } elseif ($key === 'luma_std') {
                $vector[] = min(1.0, (float) $value / 64.0);
            } elseif ($key === 'noise_residual_std' || $key === 'patch_variance_std') {
                $vector[] = min(1.0, (float) $value / 32.0);
            } else {
                $vector[] = (float) $value;
            }
        }

        return $vector;
    }

    /** @return array<int, string> */
    public function featureNames(): array
    {
        return self::FEATURES;
    }
}
