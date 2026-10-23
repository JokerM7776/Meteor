<?php
declare(strict_types=1);
namespace App\Services;

final class SiteIcon
{
    /** Older icons were uploaded straight to /uploads; newer ones come from the media library. */
    public static function validPath(string $path): bool
    {
        return self::legacy($path) || preg_match('~^/uploads/media/[a-f0-9]{32}\.(png|jpg|webp|gif)$~D', $path) === 1;
    }
    public static function legacy(string $path): bool
    {
        return preg_match('~^/uploads/[a-f0-9]{32}\.(png|jpg|webp|ico)$~D', $path) === 1;
    }
    public static function type(string $path): string
    {
        return ['png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif', 'ico' => 'image/x-icon', 'svg' => 'image/svg+xml'][pathinfo($path, PATHINFO_EXTENSION)] ?? '';
    }
    /** The icon to store: none, the current one, or a PNG/JPG/WebP/GIF from the media library. */
    public static function choose(\Core\Database $db, string $url, string $current): string
    {
        $url = trim($url);
        if ($url === '' || $url === $current) { return $url; }
        $name = substr($url, strlen(MediaLibrary::DIRECTORY));
        $row = str_starts_with($url, MediaLibrary::DIRECTORY) ? $db->one('SELECT mime_type FROM media WHERE file_name = ?', [$name]) : null;
        if (!$row || !in_array($row['mime_type'], ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true) || !self::validPath($url)) {
            throw new \InvalidArgumentException('Site icon: choose a PNG, JPG, WebP or GIF image from the media library.');
        }
        return $url;
    }
    public static function upload(?array $file): ?string
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { return null; }
        $message = 'Site icon: upload a valid PNG, JPG, WebP or ICO up to 1 MB.';
        if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 1024 * 1024 || !is_uploaded_file($file['tmp_name'])) {
            throw new \InvalidArgumentException($message);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $extension = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp',
            'image/vnd.microsoft.icon' => 'ico', 'image/x-icon' => 'ico'][$mime] ?? null;
        $valid = $extension === 'ico' ? file_get_contents($file['tmp_name'], false, null, 0, 4) === "\x00\x00\x01\x00"
            : ($extension && @getimagesize($file['tmp_name']));
        if (!$valid) { throw new \InvalidArgumentException($message); }
        $path = '/uploads/' . bin2hex(random_bytes(16)) . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], dirname(__DIR__, 2) . '/public' . $path)) { throw new \RuntimeException('Could not store site icon.'); }
        return $path;
    }
    /** Deletes an icon file that was uploaded straight to /uploads. Media library files stay. */
    public static function remove(string $path): void
    {
        if (self::legacy($path)) {
            $file = dirname(__DIR__, 2) . '/public' . $path;
            if (is_file($file)) { unlink($file); }
        }
    }
}
