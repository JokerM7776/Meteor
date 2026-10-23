<?php
declare(strict_types=1);
namespace App\Services;

final class SiteIcon
{
    public static function validPath(string $path): bool
    {
        return preg_match('~^/uploads/[a-f0-9]{32}\.(png|jpg|webp|ico)$~D', $path) === 1;
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
    public static function remove(string $path): void
    {
        if (self::validPath($path)) {
            $file = dirname(__DIR__, 2) . '/public' . $path;
            if (is_file($file)) { unlink($file); }
        }
    }
}
