<?php
declare(strict_types=1);
namespace Core;

/**
 * Re-encodes uploaded images with GD: fixes the camera orientation, shrinks very large
 * images, drops metadata (such as GPS location) and converts to WebP or AVIF.
 */
final class ImageOptimizer
{
    public const FORMATS = ['webp' => 'image/webp', 'avif' => 'image/avif'];
    private const MAX_PIXELS = 60_000_000;

    public static function available(string $format = 'webp'): bool
    {
        if (!function_exists('gd_info')) { return false; }
        $info = gd_info();
        return match ($format) {
            'webp' => function_exists('imagewebp') && !empty($info['WebP Support']),
            'avif' => function_exists('imageavif') && !empty($info['AVIF Support']),
            default => false,
        };
    }

    /**
     * Returns ['image' => [bytes, mime, width, height] or null to keep the original,
     * 'thumb' => [bytes, mime, width, height] or null, 'note' => why the original was kept].
     * $options: optimize (bool), format (webp|avif), quality (int), max_size (int, 0 = keep), thumb_size (int).
     */
    public static function process(string $file, string $mime, array $options): array
    {
        $result = ['image' => null, 'thumb' => null, 'note' => ''];
        $optimize = !empty($options['optimize']);
        $keep = static fn (string $note) => ['note' => $optimize ? $note . ' The original file was kept.' : ''] + $result;
        $size = @getimagesize($file);
        if (!$size) { return $result; }
        // GD keeps only the first frame, so animations stay untouched.
        if ($mime === 'image/gif' && preg_match_all('/\x00\x21\xF9\x04/', (string) file_get_contents($file)) > 1) {
            return ['note' => $optimize ? 'Animated GIFs are kept as they are.' : ''] + $result;
        }
        if (!self::available('webp')) { return $keep('This server cannot convert images (PHP GD with WebP support is missing).'); }
        [$width, $height] = $size;
        if ($width * $height > self::MAX_PIXELS || !self::reserveMemory($width * $height)) { return $keep('The image is too large to convert on this server.'); }
        $image = @imagecreatefromstring((string) file_get_contents($file));
        if (!$image) { return $keep('The image could not be read for conversion.'); }
        if (!imageistruecolor($image)) { imagepalettetotruecolor($image); }
        imagealphablending($image, false); imagesavealpha($image, true);
        if ($mime === 'image/jpeg') { $image = self::orient($image, $file); }

        if ($optimize) {
            $format = self::available($options['format'] ?? 'webp') ? ($options['format'] ?? 'webp') : 'webp';
            $main = self::fit($image, (int) ($options['max_size'] ?? 0));
            $bytes = self::encode($main, $format, (int) ($options['quality'] ?? 82));
            if ($bytes !== null) { $result['image'] = [$bytes, self::FORMATS[$format], imagesx($main), imagesy($main)]; }
        }
        $thumbSize = (int) ($options['thumb_size'] ?? 400);
        if (max(imagesx($image), imagesy($image)) > $thumbSize) {
            $thumb = self::fit($image, $thumbSize);
            $bytes = self::encode($thumb, 'webp', 72);
            if ($bytes !== null) { $result['thumb'] = [$bytes, 'image/webp', imagesx($thumb), imagesy($thumb)]; }
        }
        return $result;
    }

    /** GD needs about five bytes per pixel; raise the memory limit for one large photo if allowed. */
    private static function reserveMemory(int $pixels): bool
    {
        $need = $pixels * 10 + 32 * 1024 * 1024;
        $limit = self::bytes((string) ini_get('memory_limit'));
        if ($limit < 0 || $limit - memory_get_usage() >= $need) { return true; }
        @ini_set('memory_limit', (string) (memory_get_usage() + $need));
        $limit = self::bytes((string) ini_get('memory_limit'));
        return $limit < 0 || $limit - memory_get_usage() >= $need;
    }

    public static function bytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') { return -1; }
        $number = (int) $value;
        return match (strtolower(substr($value, -1))) { 'g' => $number << 30, 'm' => $number << 20, 'k' => $number << 10, default => $number };
    }

    private static function orient(\GdImage $image, string $file): \GdImage
    {
        if (!function_exists('exif_read_data')) { return $image; }
        $orientation = (int) ((@exif_read_data($file))['Orientation'] ?? 1);
        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0), 6, 5 => imagerotate($image, -90, 0), 8, 7 => imagerotate($image, 90, 0), default => $image,
        };
        if (!$rotated) { return $image; }
        if (in_array($orientation, [2, 5, 7], true)) { imageflip($rotated, IMG_FLIP_HORIZONTAL); }
        if ($orientation === 4) { imageflip($rotated, IMG_FLIP_VERTICAL); }
        return $rotated;
    }

    private static function fit(\GdImage $image, int $max): \GdImage
    {
        $width = imagesx($image); $height = imagesy($image);
        if ($max <= 0 || max($width, $height) <= $max) { return $image; }
        $scale = $max / max($width, $height);
        $newWidth = max(1, (int) round($width * $scale)); $newHeight = max(1, (int) round($height * $scale));
        $copy = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($copy, false); imagesavealpha($copy, true);
        imagefill($copy, 0, 0, imagecolorallocatealpha($copy, 0, 0, 0, 127));
        imagecopyresampled($copy, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        return $copy;
    }

    private static function encode(\GdImage $image, string $format, int $quality): ?string
    {
        $quality = max(1, min(100, $quality));
        ob_start();
        $ok = $format === 'avif' ? @imageavif($image, null, $quality, 6) : @imagewebp($image, null, $quality);
        $bytes = (string) ob_get_clean();
        return $ok && $bytes !== '' ? $bytes : null;
    }
}
