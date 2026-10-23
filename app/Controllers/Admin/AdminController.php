<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use Core\{Auth, Csrf, Database, I18n, Session, View, WebAuthn};
use App\Services\{Account, AdminSettings, ContentEditor, Languages, MenuEditor, TaxonomyEditor, UiStrings};

final class AdminController
{
    private Auth $auth;
    public function __construct(private Database $db, private I18n $i18n, private View $view, private array $config) { $this->auth = new Auth($db); }
    private function go(string $path, string $message = ''): string
    {
        if ($message !== '') { $_SESSION['flash'] = $message; }
        header('Location: ' . $path, true, $_SERVER['REQUEST_METHOD'] === 'POST' ? 303 : 302);
        return '';
    }
    private function render(string $template, string $title, array $data = []): string
    {
        $settings = new \App\Repositories\Settings($this->db, $this->i18n);
        $flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
        return $this->view->render('admin/' . $template, $data + ['title' => $title, 'flash' => $flash, 'i18n' => $this->i18n,
            'siteIcon' => $settings->icon(), 'siteTitle' => $settings->get('site_title', $this->i18n->defaultLocale(), 'Meteor'),
            'user' => $this->auth->user(), 'active' => $template, 'error' => ''], 'layouts/admin');
    }
    public function handle(string $path): string
    {
        $user = $this->auth->user();
        $open = in_array($path, ['/admin/login', '/admin/passkey/login/options', '/admin/passkey/login/verify'], true);
        if (!$user && !$open) { header('Location: /admin/login', true, 302); return ''; }
        $post = $_SERVER['REQUEST_METHOD'] === 'POST';
        $json = str_starts_with($path, '/admin/passkey/') || in_array($path, ['/admin/security/passkeys/options', '/admin/security/passkeys'], true);
        if ($post && !Csrf::verify($json ? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null) : ($_POST['csrf_token'] ?? null))) {
            http_response_code(403);
            if ($json) { header('Content-Type: application/json'); return json_encode(['error' => 'Invalid CSRF token.']); }
            return $this->render('message', 'Request rejected', ['message' => 'Invalid CSRF token. Reload the form and try again.']);
        }
        if ($json) { return $this->passkey($path, $user, $post); }
        if ($path === '/admin/login') {
            if ($user) { return $this->go('/admin/posts'); }
            if ($post) {
                if ($this->auth->password((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''), $_SERVER['REMOTE_ADDR'] ?? '')) { return $this->go('/admin/posts', 'Welcome back.'); }
                return $this->go('/admin/login', 'Unable to sign in. Check your credentials or try again later.');
            }
            return $this->render('login', 'Sign in');
        }
        if ($path === '/admin/logout' && $post) { Session::logout(); return $this->go('/admin/login', 'Signed out.'); }
        if ($path === '/admin') { return $this->go('/admin/posts'); }
        if ($path === '/admin/updates') {
            $updates = new \App\Services\AdminUpdates($this->db, dirname(__DIR__, 3));
            if ($post) {
                try { $message = $updates->submit($user, $_POST, $_FILES); }
                catch (\Throwable $e) { $message = $e->getMessage(); }
                return $this->go('/admin/updates', $message);
            }
            return $this->render('updates', 'Updates', $updates->data());
        }
        if ($path === '/admin/editor') { return $this->editor($post); }
        if (preg_match('~^/admin/(posts|pages)/(\d+)/delete$~D', $path, $m)) {
            if ($post) { (new ContentEditor($this->db))->delete($m[1] === 'pages', (int) $m[2]); return $this->go('/admin/' . $m[1], 'Deleted.'); }
            return $this->render('delete', 'Delete ' . ($m[1] === 'pages' ? 'page' : 'post'), ['action' => $path]);
        }
        if (preg_match('~^/admin/taxonomy/(category|tag)/(\d+)/delete$~D', $path, $m)) {
            $editor = new TaxonomyEditor($this->db);
            $term = $editor->find($m[1], (int) $m[2]);
            if (!$term) { http_response_code(404); return $this->render('message', 'Not found', ['message' => 'This term no longer exists.']); }
            if ($post) { $editor->delete($m[1], (int) $m[2]); return $this->go('/admin/taxonomy', ucfirst($m[1]) . ' “' . $term['slug'] . '” deleted.'); }
            return $this->render('taxonomy-delete', 'Delete ' . $m[1], ['kind' => $m[1], 'term' => $term, 'action' => $path]);
        }
        if (in_array($path, ['/admin/posts', '/admin/pages'], true) && !$post) { return $this->matrix($path === '/admin/pages'); }
        if ($path === '/admin/languages/export' && !$post) {
            $code = (string) ($_GET['code'] ?? '');
            try { $json = (new UiStrings($this->db))->export($code); }
            catch (\InvalidArgumentException $e) { http_response_code(404); return $this->render('message', 'Not found', ['message' => $e->getMessage()]); }
            header('Content-Type: application/json; charset=UTF-8');
            header('Content-Disposition: attachment; filename="meteor-language-' . $code . '.json"');
            header('X-Content-Type-Options: nosniff');
            return $json;
        }
        if ($path === '/admin/languages' && $post && ($_POST['action'] ?? '') === 'import') {
            $file = $_FILES['language_file'] ?? [];
            try {
                if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '') || ($file['size'] ?? 0) > \Core\LanguagePack::MAX_BYTES) {
                    throw new \InvalidArgumentException('Choose a Meteor language file (.json, up to 256 KB).');
                }
                $message = (new UiStrings($this->db))->import((string) file_get_contents($file['tmp_name']), !empty($_POST['update_names']));
            } catch (\InvalidArgumentException $e) { $message = $e->getMessage(); }
            return $this->go('/admin/languages', $message);
        }
        $section = match ($path) { '/admin/languages' => 'languages', '/admin/menus' => 'menus', '/admin/settings' => 'settings', '/admin/strings' => 'strings', '/admin/taxonomy' => 'taxonomy', '/admin/security' => 'security', default => '' };
        if ($section !== '') {
            $error = ''; $old = $_SESSION['old'][$section] ?? []; unset($_SESSION['old'][$section]);
            if ($post) {
                try {
                    match ($section) {
                        'languages' => (new Languages($this->db))->save($_POST['action'] ?? 'add', $_POST),
                        'settings' => (new AdminSettings($this->db))->save($_POST, $_FILES['site_icon'] ?? null),
                        'strings' => $this->saveStrings(),
                        'menus' => (new MenuEditor($this->db))->save((string) ($_POST['menu'] ?? ''), $_POST),
                        'taxonomy' => (new TaxonomyEditor($this->db))->save($_POST),
                        'security' => (new Account($this->db))->update($user, $_POST['action'] ?? '', $_POST),
                    };
                    return $this->go($path . $this->query($section), $section === 'security' && ($_POST['action'] ?? '') === 'remove' ? 'Passkey removed. Password sign-in remains available.' : 'Changes saved.');
                } catch (\InvalidArgumentException $e) {
                    $safe = $_POST; foreach (['current_password', 'new_password', 'new_password_confirm'] as $key) { unset($safe[$key]); }
                    $_SESSION['old'][$section] = $safe;
                    return $this->go($path . $this->query($section), $e->getMessage());
                }
            }
            $locale = (string) ($_GET['locale'] ?? $this->i18n->defaultLocale());
            if (!isset($this->i18n->registry()[$locale])) { $locale = $this->i18n->defaultLocale(); }
            $data = ['db' => $this->db, 'locale' => $locale, 'old' => $old];
            if ($section === 'menus') {
                $menus = new \App\Repositories\Menus($this->db); $menus->ensureDefaults();
                $data['menus'] = ['header' => $menus->rows('header'), 'footer' => $menus->rows('footer')];
            }
            return $this->render($section, ['taxonomy' => 'Categories & tags', 'menus' => 'Header & footer', 'strings' => 'Interface text'][$section] ?? ucfirst($section), $data);
        }
        http_response_code(404); return $this->render('message', 'Not found', ['message' => 'This admin page does not exist.']);
    }
    /** Query string to return to after saving a section, so the admin lands on the same view. */
    private function query(string $section): string
    {
        $keep = match ($section) { 'taxonomy' => ['q'], 'strings' => ['q', 'missing'], default => [] };
        $query = [];
        foreach ($keep as $key) { if (is_string($_GET[$key] ?? null) && trim($_GET[$key]) !== '') { $query[$key] = trim($_GET[$key]); } }
        return $query ? '?' . http_build_query($query) : '';
    }
    private function saveStrings(): void
    {
        // PHP silently drops fields past max_input_vars; refuse rather than save half a table.
        $limit = (int) ini_get('max_input_vars');
        if ($limit > 0 && count($_POST, COUNT_RECURSIVE) >= $limit - 1) {
            throw new \InvalidArgumentException('Too many fields to save at once. Filter the table (search or “Only rows with missing text”) and save again.');
        }
        (new UiStrings($this->db))->save(is_array($_POST['strings'] ?? null) ? $_POST['strings'] : []);
    }
    private function passkey(string $path, ?array $user, bool $post): string
    {
        header('Content-Type: application/json; charset=UTF-8');
        if (!$post) { http_response_code(405); return json_encode(['error' => 'POST required.']); }
        $web = new WebAuthn($this->db, $this->config['app_url'], $this->config['webauthn']['rp_id'], $this->config['webauthn']['rp_name']);
        try {
            $data = json_decode(file_get_contents('php://input'), true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($data)) { throw new \InvalidArgumentException('Invalid request.'); }
            if ($path === '/admin/passkey/login/options') { return json_encode($web->authenticationOptions()); }
            if ($path === '/admin/security/passkeys/options') { return json_encode($web->registrationOptions($user)); }
            if ($path === '/admin/security/passkeys') {
                $web->register($data, $user, $data['name'] ?? ''); $_SESSION['flash'] = 'Passkey added.';
                return json_encode(['redirect' => '/admin/security']);
            }
            $user = $web->authenticate($data);
            Session::login((int) $user['id'], (int) $user['session_version']);
            return json_encode(['redirect' => '/admin/posts']);
        } catch (\Throwable $e) {
            http_response_code(400); return json_encode(['error' => 'Unable to verify the passkey. Try again or use your password.']);
        }
    }
    private function matrix(bool $page): string
    {
        [$table, $translations, $foreign] = ContentEditor::tables($page);
        $locales = array_filter($this->i18n->registry(), fn ($l) => $l['status'] !== 'off');
        $q = trim((string) ($_GET['q'] ?? '')); $filter = $_GET['filter'] ?? 'all';
        $items = $this->db->all("SELECT * FROM $table ORDER BY updated_at DESC, id DESC");
        $counts = ['all' => 0, 'missing' => 0, 'drafts' => 0]; $rows = [];
        foreach ($items as $item) {
            $ts = $this->db->all("SELECT locale, title, status FROM $translations WHERE $foreign = ?", [$item['id']]);
            $item['translations'] = array_column($ts, null, 'locale');
            if ($q !== '' && !str_contains(mb_strtolower($item['slug'] . ' ' . implode(' ', array_column($ts, 'title'))), mb_strtolower($q))) { continue; }
            $missing = count(array_intersect_key($item['translations'], $locales)) < count($locales);
            $drafts = in_array('draft', array_column(array_intersect_key($item['translations'], $locales), 'status'), true);
            $counts['all']++; $counts['missing'] += (int) $missing; $counts['drafts'] += (int) $drafts;
            if (($filter === 'missing' && !$missing) || ($filter === 'drafts' && !$drafts)) { continue; }
            $rows[] = $item;
        }
        $total = count($rows); $number = max(1, min((int) ($_GET['page'] ?? 1), max(1, (int) ceil($total / 20))));
        return $this->render('matrix', $page ? 'Pages' : 'Posts', ['rows' => array_slice($rows, ($number - 1) * 20, 20), 'locales' => $locales,
            'pageContent' => $page, 'q' => $q, 'filter' => $filter, 'counts' => $counts, 'number' => $number, 'total' => $total]);
    }
    private function editor(bool $post): string
    {
        $page = ($_GET['kind'] ?? '') === 'page'; $id = (int) ($_GET['post'] ?? $_GET['id'] ?? 0);
        [$table, $translations, $foreign] = ContentEditor::tables($page);
        $locale = (string) ($_GET['locale'] ?? $this->i18n->defaultLocale());
        if (!isset($this->i18n->registry()[$locale])) { http_response_code(404); return $this->render('message', 'Not found', ['message' => 'Unknown language.']); }
        $item = $id ? $this->db->one("SELECT * FROM $table WHERE id = ?", [$id]) : ['base_locale' => $locale, 'is_published' => 1];
        if (!$item) { http_response_code(404); return $this->render('message', 'Not found', ['message' => 'Content not found.']); }
        $target = '/admin/editor?' . http_build_query(['post' => $id, 'locale' => $locale, 'kind' => $page ? 'page' : 'post', 'from' => $_GET['from'] ?? $item['base_locale']]);
        if ($post) {
            try {
                $saved = (new ContentEditor($this->db))->save($page, $id, $locale, $_POST, $_FILES['cover'] ?? null);
                return $this->go('/admin/editor?' . http_build_query(['post' => $saved, 'locale' => $locale, 'kind' => $page ? 'page' : 'post']), 'Translation saved.');
            } catch (\InvalidArgumentException $e) {
                $_SESSION['editor_old'] = ['target' => $target, 'values' => $_POST, 'error' => $e->getMessage()];
                return $this->go($target);
            }
        }
        $ts = array_column($this->db->all("SELECT * FROM $translations WHERE $foreign = ?", [$id]), null, 'locale');
        $values = ($ts[$locale] ?? []) + $item;
        if (!$page) { $tags = $this->db->all('SELECT t.slug, tt.name FROM tags t JOIN post_tags pt ON pt.tag_id = t.id LEFT JOIN tag_translations tt ON tt.tag_id = t.id AND tt.locale = ? WHERE pt.post_id = ?', [$locale, $id]);
            $values['tags'] = implode(', ', array_map(fn ($t) => $t['name'] ?? $t['slug'], $tags)); }
        $error = ''; $old = $_SESSION['editor_old'] ?? null; unset($_SESSION['editor_old']);
        if ($old && $old['target'] === $target) { $values = $old['values'] + ['is_pinned' => 0, 'is_published' => 0, 'show_in_nav' => 0] + $values; $error = $old['error']; }
        $from = (string) ($_GET['from'] ?? $item['base_locale']);
        return $this->render('editor', $id ? 'Edit ' . ($page ? 'page' : 'post') : 'New ' . ($page ? 'page' : 'post'),
            compact('page', 'id', 'locale', 'item', 'values', 'error', 'target', 'ts', 'from') + ['reference' => $ts[$from] ?? [], 'categories' => $this->db->all('SELECT * FROM categories ORDER BY sort_order, slug')]);
    }
}
