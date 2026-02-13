<?php

declare(strict_types=1);

namespace ML\IDEA\Vision;

use ML\IDEA\Exceptions\InvalidArgumentException;

final class ImageFeatureExtractor
{
    /**
     * @param array<int, array<int, array{0:int|float,1:int|float,2:int|float}>> $rgbMatrix
     * @return array<int, array{0:float,1:float,2:float}>
     */
    public static function fromRgbMatrix(array $rgbMatrix, int $maxSamples = 5000): array
    {
        if ($rgbMatrix === []) {
            throw new InvalidArgumentException('RGB matrix cannot be empty.');
        }

        $flat = [];
        foreach ($rgbMatrix as $row) {
            foreach ($row as $pixel) {
                $flat[] = [
                    self::clamp((float) $pixel[0]),
                    self::clamp((float) $pixel[1]),
                    self::clamp((float) $pixel[2]),
                ];
            }
        }

        return self::sample($flat, $maxSamples);
    }

    /**
     * @return array<int, array{0:float,1:float,2:float}>
     */
    public static function fromImageFile(string $path, int $maxSamples = 5000): array
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException(sprintf('Image file not found: %s', $path));
        }

        if (!function_exists('imagecreatefromstring') || !function_exists('imagecolorat')) {
            throw new InvalidArgumentException('GD extension is required for fromImageFile().');
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new InvalidArgumentException(sprintf('Unable to read image file: %s', $path));
        }

        $img = @imagecreatefromstring($raw);
        if ($img === false) {
            throw new InvalidArgumentException(sprintf('Unsupported or invalid image: %s', $path));
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $flat = [];

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgb = imagecolorat($img, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $flat[] = [(float) $r, (float) $g, (float) $b];
            }
        }

        imagedestroy($img);
        return self::sample($flat, $maxSamples);
    }

    /**
     * @param array<int, array{0:float,1:float,2:float}> $flat
     * @return array<int, array{0:float,1:float,2:float}>
     */
    private static function sample(array $flat, int $maxSamples): array
    {
        $n = count($flat);
        if ($n === 0) {
            return [];
        }

        $limit = max(1, $maxSamples);
        if ($n <= $limit) {
            return $flat;
        }

        $stride = (int) ceil($n / $limit);
        $sampled = [];
        for ($i = 0; $i < $n; $i += $stride) {
            $sampled[] = $flat[$i];
        }

        return $sampled;
    }

    private static function clamp(float $v): float
    {
        return max(0.0, min(255.0, $v));
    }
}
