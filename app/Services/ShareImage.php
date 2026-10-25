<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\Settings;
use Core\Database;

/**
 * The picture shown when a link is shared (Open Graph / Twitter cards): a post's cover, or else the
 * site's default share image when Settings → Social sharing turns it on.
 */
final class ShareImage
{
    /** ['url', 'width', 'height', 'type', 'alt'] for the page, or null for no image. */
    public static function pick(Database $db, Settings $settings, ?string $cover): ?array
    {
        $path = trim((string) $cover);
        if ($path === '' && $settings->global('share_image_default', '0') === '1') { $path = $settings->global('share_image'); }
        if ($path === '' || !str_starts_with($path, '/uploads/')) { return null; }
        $row = str_starts_with($path, MediaLibrary::DIRECTORY)
            ? $db->one('SELECT width, height, mime_type, alt_text FROM media WHERE file_name = ?', [substr($path, strlen(MediaLibrary::DIRECTORY))]) : null;
        return ['url' => \absolute_url($path), 'width' => (int) ($row['width'] ?? 0), 'height' => (int) ($row['height'] ?? 0),
            'type' => (string) ($row['mime_type'] ?? ''), 'alt' => (string) ($row['alt_text'] ?? '')];
    }

    /** The default image to store: none, the current one, or a PNG/JPG/WebP/GIF from the media library. */
    public static function choose(Database $db, string $url, string $current): string
    {
        $url = trim($url);
        if ($url === '' || $url === $current) { return $url; }
        $row = str_starts_with($url, MediaLibrary::DIRECTORY) ? $db->one('SELECT mime_type FROM media WHERE file_name = ?', [substr($url, strlen(MediaLibrary::DIRECTORY))]) : null;
        if (!$row || !in_array($row['mime_type'], ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true)
            || !preg_match('~^/uploads/media/[a-f0-9]{32}\.(png|jpg|webp|gif)$~D', $url)) {
            throw new \InvalidArgumentException('Share image: choose a PNG, JPG, WebP or GIF image from the media library.');
        }
        return $url;
    }
}
