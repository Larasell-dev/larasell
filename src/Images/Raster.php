<?php

namespace Larasell\Larasell\Images;

use GdImage;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Larasell\Larasell\Models\ProductImage;

final class Raster
{
    public static function decode(ProductImage $image): ?GdImage
    {
        if ($image->isSvg() || ! extension_loaded('gd')) {
            return null;
        }

        $path = $image->getAttribute('path');

        if (! is_string($path) || $path === '') {
            return null;
        }

        $disk = Storage::disk(config('larasell.images.disk'));

        if (! $disk->exists($path)) {
            return null;
        }

        $contents = $disk->get($path);

        if (! is_string($contents) || $contents === '') {
            return null;
        }

        $decoded = @imagecreatefromstring($contents);

        return $decoded instanceof GdImage ? $decoded : null;
    }

    public static function averageColor(GdImage $image): string
    {
        $sample = self::resample($image, 1, 1);
        $rgb = imagecolorat($sample, 0, 0);
        imagedestroy($sample);

        if ($rgb === false) {
            return '#ffffff';
        }

        return sprintf('#%02x%02x%02x', ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF);
    }

    public static function jpegDataUri(GdImage $image, int $maxEdge = 16, int $quality = 50): ?string
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = $maxEdge / max($width, $height, 1);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $resized = self::resample($image, $targetWidth, $targetHeight);

        ob_start();
        $encoded = imagejpeg($resized, null, $quality);
        $binary = ob_get_clean();
        imagedestroy($resized);

        if ($encoded === false || ! is_string($binary) || $binary === '') {
            return null;
        }

        return 'data:image/jpeg;base64,'.base64_encode($binary);
    }

    private static function resample(GdImage $image, int $width, int $height): GdImage
    {
        if ($width < 1 || $height < 1) {
            throw new InvalidArgumentException('Raster resampling requires a positive width and height.');
        }

        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);

        if ($white !== false) {
            imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        }

        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));

        return $canvas;
    }
}
