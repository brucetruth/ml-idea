<?php

declare(strict_types=1);

namespace ML\IDEA\Vision\Support;

/** GD-based synthetic images for tests and demos (no external assets). */
final class VisionTestImages
{
    public static function createFlatAiLike(string $path, int $width = 64, int $height = 64): string
    {
        return self::write($path, $width, $height, static function (int $x, int $y): array {
            $v = 128 + (($x + $y) % 8);

            return [$v, $v - 4, $v - 8];
        });
    }

    public static function createTexturedAuthentic(string $path, int $width = 64, int $height = 64): string
    {
        return self::write($path, $width, $height, static function (int $x, int $y): array {
            $r = (int) (80 + 40 * sin($x * 0.35) + 20 * cos($y * 0.21));
            $g = (int) (90 + 35 * cos($x * 0.17) + 25 * sin($y * 0.29));
            $b = (int) (70 + 30 * sin($x * 0.23 + $y * 0.11));

            return [max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b))];
        });
    }

    /** @param callable(int, int): array{0:int,1:int,2:int} $pixel */
    private static function write(string $path, int $width, int $height, callable $pixel): string
    {
        if (!function_exists('imagecreatetruecolor')) {
            throw new \RuntimeException('GD extension required for VisionTestImages.');
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $img = imagecreatetruecolor($width, $height);
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                [$r, $g, $b] = $pixel($x, $y);
                $color = imagecolorallocate($img, $r, $g, $b);
                imagesetpixel($img, $x, $y, $color);
            }
        }

        imagepng($img, $path);
        imagedestroy($img);

        return $path;
    }
}
