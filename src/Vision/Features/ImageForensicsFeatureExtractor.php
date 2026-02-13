<?php

declare(strict_types=1);

namespace ML\IDEA\Vision\Features;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Vision\ImageFeatureExtractor;

final class ImageForensicsFeatureExtractor
{
    /**
     * @param array<int, array{0:float|int,1:float|int,2:float|int}> $rgbSamples
     * @return array<string, float|int|bool|string>
     */
    public function fromRgbSamples(array $rgbSamples): array
    {
        if ($rgbSamples === []) {
            throw new InvalidArgumentException('rgbSamples cannot be empty.');
        }

        $n = count($rgbSamples);
        $sumR = 0.0;
        $sumG = 0.0;
        $sumB = 0.0;
        $sumL = 0.0;
        $sumL2 = 0.0;
        $sumSat = 0.0;
        $clippingChannels = 0;
        $unique = [];

        foreach ($rgbSamples as $s) {
            $r = self::clamp((float) $s[0]);
            $g = self::clamp((float) $s[1]);
            $b = self::clamp((float) $s[2]);

            $sumR += $r;
            $sumG += $g;
            $sumB += $b;

            $l = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
            $sumL += $l;
            $sumL2 += $l * $l;

            $max = max($r, $g, $b);
            $min = min($r, $g, $b);
            $sat = $max <= 0.0 ? 0.0 : (($max - $min) / $max);
            $sumSat += $sat;

            foreach ([$r, $g, $b] as $c) {
                if ($c <= 3.0 || $c >= 252.0) {
                    $clippingChannels++;
                }
            }

            $key = (int) floor($r / 16.0) . ':' . (int) floor($g / 16.0) . ':' . (int) floor($b / 16.0);
            $unique[$key] = true;
        }

        $meanL = $sumL / $n;
        $varL = max(0.0, ($sumL2 / $n) - ($meanL * $meanL));
        $stdL = sqrt($varL);

        return [
            'sample_count' => $n,
            'mean_r' => $sumR / $n,
            'mean_g' => $sumG / $n,
            'mean_b' => $sumB / $n,
            'luma_std' => $stdL,
            'saturation_mean' => $sumSat / $n,
            'clipping_ratio' => $clippingChannels / (3.0 * $n),
            'color_diversity' => count($unique) / $n,
            'flatness_score' => 1.0 - min(1.0, $stdL / 64.0),
        ];
    }

    /**
     * @return array<string, float|int|bool|string>
     */
    public function fromImageFile(string $path, int $maxSamples = 5000): array
    {
        $samples = ImageFeatureExtractor::fromImageFile($path, $maxSamples);
        $features = $this->fromRgbSamples($samples);

        $features['source_path'] = $path;
        $features['filename_hint_score'] = $this->filenameHintScore($path);

        $size = @getimagesize($path);
        if (is_array($size)) {
            $features['width'] = (int) $size[0];
            $features['height'] = (int) $size[1];
            $features['mime'] = (string) $size['mime'];
        } else {
            $features['width'] = 0;
            $features['height'] = 0;
            $features['mime'] = '';
        }

        $meta = $this->extractMetadataSignals($path);
        foreach ($meta as $k => $v) {
            $features[$k] = $v;
        }

        return $features;
    }

    /**
     * @return array<string, float|int|bool|string>
     */
    private function extractMetadataSignals(string $path): array
    {
        $result = [
            'has_exif_camera' => false,
            'generator_hint_score' => 0.0,
        ];

        if (!function_exists('exif_read_data')) {
            return $result;
        }

        /** @var array<string, mixed>|false $exif */
        $exif = @exif_read_data($path, null, true, false);
        if (!is_array($exif)) {
            return $result;
        }

        $cameraKeys = ['Model', 'Make'];
        foreach ($cameraKeys as $key) {
            foreach ($exif as $section) {
                if (is_array($section) && isset($section[$key]) && trim((string) $section[$key]) !== '') {
                    $result['has_exif_camera'] = true;
                }
            }
        }

        $flat = strtolower(json_encode($exif, JSON_THROW_ON_ERROR));
        $hints = ['stable diffusion', 'midjourney', 'dall-e', 'dalle', 'sdxl', 'comfyui', 'invokeai', 'adobe firefly', 'flux'];
        $score = 0.0;
        foreach ($hints as $hint) {
            if (str_contains($flat, $hint)) {
                $score += 0.25;
            }
        }

        $result['generator_hint_score'] = min(1.0, $score);
        return $result;
    }

    private function filenameHintScore(string $path): float
    {
        $name = strtolower(basename($path));
        $hints = ['midjourney', 'dalle', 'stable-diffusion', 'sdxl', 'flux', 'ai-generated', 'genimg'];

        $score = 0.0;
        foreach ($hints as $hint) {
            if (str_contains($name, $hint)) {
                $score += 0.2;
            }
        }

        return min(1.0, $score);
    }

    private static function clamp(float $v): float
    {
        return max(0.0, min(255.0, $v));
    }
}
