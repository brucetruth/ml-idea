<?php

declare(strict_types=1);

namespace ML\IDEA\Vision\Features;

use ML\IDEA\Exceptions\InvalidArgumentException;
use ML\IDEA\Vision\Contracts\VisionFeatureBackendInterface;
use ML\IDEA\Vision\ImageFeatureExtractor;

final class ImageForensicsFeatureExtractor
{
    public function __construct(
        private readonly ?VisionFeatureBackendInterface $backend = null,
    ) {
    }
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
     * Spatial forensics from an RGB matrix (DCT high-frequency ratio, noise residual, patch variance).
     *
     * @param array<int, array<int, array{0:float|int,1:float|int,2:float|int}>> $rgbMatrix
     * @return array<string, float|int|bool|string>
     */
    public function fromRgbMatrix(array $rgbMatrix, int $maxSamples = 5000): array
    {
        if ($rgbMatrix === []) {
            throw new InvalidArgumentException('rgbMatrix cannot be empty.');
        }

        $flat = ImageFeatureExtractor::fromRgbMatrix($rgbMatrix, $maxSamples);
        $features = $this->fromRgbSamples($flat);

        $luma = $this->lumaGrid($rgbMatrix);
        $features['dct_high_freq_ratio'] = $this->dctHighFrequencyRatio($luma);
        $features['noise_residual_std'] = $this->noiseResidualStd($luma);
        $features['patch_variance_std'] = $this->patchVarianceStd($luma);

        return $features;
    }

    /**
     * @return array<string, float|int|bool|string>
     */
    public function fromImageFile(string $path, int $maxSamples = 5000): array
    {
        $matrix = ImageFeatureExtractor::rgbMatrixFromImageFile($path);
        $features = $this->fromRgbMatrix($matrix, $maxSamples);

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

        if ($this->backend !== null) {
            $features = $this->backend->enrichSignals($features, $path);
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

    /**
     * @param array<int, array<int, array{0:float|int,1:float|int,2:float|int}>> $rgbMatrix
     * @return array<int, array<int, float>>
     */
    private function lumaGrid(array $rgbMatrix): array
    {
        $grid = [];
        foreach ($rgbMatrix as $row) {
            $lumaRow = [];
            foreach ($row as $pixel) {
                $lumaRow[] = 0.2126 * (float) $pixel[0] + 0.7152 * (float) $pixel[1] + 0.0722 * (float) $pixel[2];
            }
            $grid[] = $lumaRow;
        }

        return $grid;
    }

    /** @param array<int, array<int, float>> $luma */
    private function dctHighFrequencyRatio(array $luma, int $blockSize = 8): float
    {
        $h = count($luma);
        $w = count($luma[0] ?? []);
        if ($h < $blockSize || $w < $blockSize) {
            return 0.0;
        }

        $lowEnergy = 0.0;
        $highEnergy = 0.0;
        $blocks = 0;

        for ($by = 0; $by <= $h - $blockSize; $by += $blockSize) {
            for ($bx = 0; $bx <= $w - $blockSize; $bx += $blockSize) {
                $coeffs = $this->dct2dBlock($luma, $bx, $by, $blockSize);
                foreach ($coeffs as $i => $c) {
                    $row = intdiv($i, $blockSize);
                    $col = $i % $blockSize;
                    $energy = $c * $c;
                    if ($row + $col <= 2) {
                        $lowEnergy += $energy;
                    } else {
                        $highEnergy += $energy;
                    }
                }
                $blocks++;
            }
        }

        if ($blocks === 0) {
            return 0.0;
        }

        $total = $lowEnergy + $highEnergy;

        return $total > 0.0 ? $highEnergy / $total : 0.0;
    }

    /** @param array<int, array<int, float>> $luma */
    private function noiseResidualStd(array $luma): float
    {
        $h = count($luma);
        $w = count($luma[0] ?? []);
        if ($h < 3 || $w < 3) {
            return 0.0;
        }

        $residuals = [];
        for ($y = 1; $y < $h - 1; $y++) {
            for ($x = 1; $x < $w - 1; $x++) {
                $neighbors = [
                    $luma[$y - 1][$x - 1], $luma[$y - 1][$x], $luma[$y - 1][$x + 1],
                    $luma[$y][$x - 1], $luma[$y][$x + 1],
                    $luma[$y + 1][$x - 1], $luma[$y + 1][$x], $luma[$y + 1][$x + 1],
                ];
                $mean = array_sum($neighbors) / count($neighbors);
                $residuals[] = $luma[$y][$x] - $mean;
            }
        }

        if ($residuals === []) {
            return 0.0;
        }

        $mean = array_sum($residuals) / count($residuals);
        $var = 0.0;
        foreach ($residuals as $r) {
            $d = $r - $mean;
            $var += $d * $d;
        }

        return sqrt($var / count($residuals));
    }

    /** @param array<int, array<int, float>> $luma */
    private function patchVarianceStd(array $luma, int $patchSize = 8): float
    {
        $h = count($luma);
        $w = count($luma[0] ?? []);
        if ($h < $patchSize || $w < $patchSize) {
            return 0.0;
        }

        $variances = [];
        for ($by = 0; $by <= $h - $patchSize; $by += $patchSize) {
            for ($bx = 0; $bx <= $w - $patchSize; $bx += $patchSize) {
                $values = [];
                for ($y = $by; $y < $by + $patchSize; $y++) {
                    for ($x = $bx; $x < $bx + $patchSize; $x++) {
                        $values[] = $luma[$y][$x];
                    }
                }
                $mean = array_sum($values) / count($values);
                $var = 0.0;
                foreach ($values as $v) {
                    $d = $v - $mean;
                    $var += $d * $d;
                }
                $variances[] = $var / count($values);
            }
        }

        if ($variances === []) {
            return 0.0;
        }

        $meanVar = array_sum($variances) / count($variances);
        $spread = 0.0;
        foreach ($variances as $v) {
            $d = $v - $meanVar;
            $spread += $d * $d;
        }

        return sqrt($spread / count($variances));
    }

    /** @return array<int, float> flattened DCT coefficients */
    private function dct2dBlock(array $luma, int $bx, int $by, int $size): array
    {
        $block = [];
        for ($y = 0; $y < $size; $y++) {
            $row = [];
            for ($x = 0; $x < $size; $x++) {
                $row[] = $luma[$by + $y][$bx + $x];
            }
            $block[] = $row;
        }

        for ($y = 0; $y < $size; $y++) {
            $block[$y] = $this->dct1dVector($block[$y]);
        }

        for ($x = 0; $x < $size; $x++) {
            $col = [];
            for ($y = 0; $y < $size; $y++) {
                $col[] = $block[$y][$x];
            }
            $col = $this->dct1dVector($col);
            for ($y = 0; $y < $size; $y++) {
                $block[$y][$x] = $col[$y];
            }
        }

        $flat = [];
        foreach ($block as $row) {
            foreach ($row as $value) {
                $flat[] = $value;
            }
        }

        return $flat;
    }

    /** @param array<int, float> $data */
    private function dct1dVector(array $data): array
    {
        $n = count($data);
        $out = array_fill(0, $n, 0.0);
        for ($k = 0; $k < $n; $k++) {
            $sum = 0.0;
            for ($i = 0; $i < $n; $i++) {
                $sum += $data[$i] * cos(M_PI * $k * (2 * $i + 1) / (2 * $n));
            }
            $out[$k] = $sum;
        }

        return $out;
    }

    private static function clamp(float $v): float
    {
        return max(0.0, min(255.0, $v));
    }
}
