<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Services\MediaLibrary;
use Core\Database;

/** /admin/media, the editor's media picker endpoints and Settings → Optimization. */
final class MediaController
{
    public const PER_PAGE = 20;
    public const PICKER_PAGE = 24;
    private MediaLibrary $media;

    public function __construct(private Database $db, private \Closure $render, private \Closure $go, string $public)
    {
        $this->media = new MediaLibrary($db, $public);
    }

    public function handle(string $path, bool $post): ?string
    {
        if ($path === '/admin/media') { return $post ? $this->submit() : $this->index(); }
        if ($path === '/admin/media/library' && !$post) { return $this->library(); }
        if ($path === '/admin/media/upload' && $post) { return $this->pickerUpload(); }
        if ($path === '/admin/optimization') { return $this->optimization($post); }
        if (preg_match('~^/admin/media/(\d+)/delete$~D', $path, $m)) {
            $row = $this->media->find((int) $m[1]);
            if (!$row) { http_response_code(404); return ($this->render)('message', 'Not found', ['message' => 'This image no longer exists.']); }
            if ($post) { $this->media->delete((int) $row['id']); return ($this->go)('/admin/media', '“' . $row['original_name'] . '” deleted.'); }
            return ($this->render)('media-delete', 'Delete image', ['row' => $row, 'usage' => $this->media->usage($row), 'action' => $path]);
        }
        return null;
    }

    private function filters(): array
    {
        $text = static fn (string $key) => is_string($_GET[$key] ?? null) ? trim($_GET[$key]) : '';
        return ['q' => $text('q'), 'category' => $text('category'), 'page' => max(1, (int) $text('page'))];
    }

    private function back(array $filters): string
    {
        $query = array_filter(['q' => $filters['q'], 'category' => $filters['category'], 'page' => $filters['page'] > 1 ? $filters['page'] : '']);
        return '/admin/media' . ($query ? '?' . http_build_query($query) : '');
    }

    private function index(): string
    {
        $filters = $this->filters();
        $old = $_SESSION['old']['media'] ?? []; unset($_SESSION['old']['media']);
        return ($this->render)('media', 'Media', $filters + ['result' => $this->media->search($filters['q'], $filters['category'], $filters['page'], self::PER_PAGE),
            'categories' => $this->media->categories(), 'options' => $this->media->options(), 'old' => $old]);
    }

    private function submit(): string
    {
        $filters = $this->filters();
        try {
            if (($_POST['action'] ?? '') === 'save') {
                $this->media->update((int) ($_POST['id'] ?? 0), $_POST);
                return ($this->go)($this->back($filters), 'Image details saved.');
            }
            $files = self::files($_FILES['media'] ?? []);
            if (!$files) { throw new \InvalidArgumentException('Choose one or more images to upload.'); }
            $done = 0; $problems = [];
            foreach ($files as $file) {
                try { $row = $this->media->upload($file, $_POST); $done++; if ($row['note'] !== '') { $problems[] = $row['original_name'] . ': ' . $row['note']; } }
                catch (\InvalidArgumentException | \RuntimeException $e) { $problems[] = ($file['name'] ?: 'image') . ': ' . $e->getMessage(); }
            }
            $message = ($done === 1 ? '1 image uploaded.' : $done . ' images uploaded.') . ($problems ? ' ' . implode(' ', $problems) : '');
            if (!$done) { $_SESSION['old']['media'] = array_intersect_key($_POST, array_flip(['alt', 'category', 'tags'])); }
            return ($this->go)($done ? '/admin/media' : $this->back($filters), $message);
        } catch (\InvalidArgumentException $e) {
            $_SESSION['old']['media'] = array_intersect_key($_POST, array_flip(['id', 'alt', 'category', 'tags']));
            return ($this->go)($this->back($filters), $e->getMessage());
        }
    }

    /** $_FILES['media'] as a list of single files, for both name="media" and name="media[]". */
    private static function files(mixed $upload): array
    {
        if (!is_array($upload) || !isset($upload['error'])) { return []; }
        if (!is_array($upload['error'])) { return $upload['error'] === UPLOAD_ERR_NO_FILE ? [] : [$upload]; }
        $files = [];
        foreach (array_keys($upload['error']) as $i) {
            $file = []; foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $key) { $file[$key] = $upload[$key][$i] ?? null; }
            if (is_int($file['error']) && $file['error'] !== UPLOAD_ERR_NO_FILE) { $files[] = $file; }
        }
        return array_slice($files, 0, 20);
    }

    private function json(array $data, int $status = 200): string
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function library(): string
    {
        $filters = $this->filters();
        $result = $this->media->search($filters['q'], $filters['category'], $filters['page'], self::PICKER_PAGE);
        return $this->json(['items' => array_map(MediaLibrary::json(...), $result['rows']), 'page' => $result['page'], 'pages' => $result['pages'],
            'total' => $result['total'], 'categories' => $this->media->categories()]);
    }

    private function pickerUpload(): string
    {
        try {
            $files = self::files($_FILES['media'] ?? []);
            if (count($files) !== 1) { throw new \InvalidArgumentException('Choose one image to upload.'); }
            $row = $this->media->upload($files[0], $_POST);
            return $this->json(['item' => MediaLibrary::json($row), 'note' => $row['note']]);
        } catch (\InvalidArgumentException | \RuntimeException $e) { return $this->json(['error' => $e->getMessage()], 422); }
    }

    private function optimization(bool $post): string
    {
        if ($post) {
            try {
                if (($_POST['section'] ?? '') === 'fonts') { \App\Services\Fonts::save($this->db, $_POST); } else { $this->media->saveOptions($_POST); }
                return ($this->go)('/admin/optimization', 'Changes saved.');
            }
            catch (\InvalidArgumentException $e) { $_SESSION['old']['optimization'] = $_POST; return ($this->go)('/admin/optimization', $e->getMessage()); }
        }
        $old = $_SESSION['old']['optimization'] ?? []; unset($_SESSION['old']['optimization']);
        return ($this->render)('optimization', 'Optimization', ['options' => $this->media->options(), 'old' => $old,
            'mediaCount' => (int) $this->db->one('SELECT COUNT(*) AS n FROM media')['n'],
            'fontSource' => \App\Services\Fonts::source($this->db->one('SELECT value FROM settings WHERE `key` = ? AND locale_scope = ?', ['font_source', ''])['value'] ?? 'local')]);
    }
}
