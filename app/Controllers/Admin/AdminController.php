<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use Core\{Auth, Csrf, Database, I18n, Session, View, WebAuthn};
use App\Services\{Account, AdminSettings, Appearance, Author, ContentEditor, HomePage, LanguageDownloads, Languages, LoginProtection, MenuEditor, TaxonomyEditor, UiStrings};

final class AdminController
{
    private const PER_PAGE = 20;
    /** Sort orders of the posts and pages lists; posts also sort by publish date. */
    private const SORTS = ['updated' => 'Last updated', 'created' => 'Newest first', 'oldest' => 'Oldest first', 'title' => 'Title A–Z', 'title_desc' => 'Title Z–A'];
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
            'user' => $this->auth->user(), 'active' => $template, 'pendingComments' => (new \App\Repositories\Comments($this->db))->pendingCount(), 'fontSource' => $settings->global('font_source', 'local'), 'error' => ''], 'layouts/admin');
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
            // PHP drops the whole form, token included, when a request is larger than post_max_size.
            $tooLarge = !$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > \Core\ImageOptimizer::bytes((string) ini_get('post_max_size'));
            $message = $tooLarge ? 'The upload is larger than this server accepts (PHP post_max_size is ' . ini_get('post_max_size') . ').' : 'Invalid CSRF token. Reload the form and try again.';
            if ($json || $path === '/admin/media/upload') { header('Content-Type: application/json'); return json_encode(['error' => $message]); }
            return $this->render('message', 'Request rejected', ['message' => $message]);
        }
        if ($json) { return $this->passkey($path, $user, $post); }
        if ($path === '/admin/login') {
            if ($user) { return $this->go('/admin/posts'); }
            $protection = $this->protection();
            if ($post) {
                if (!$protection->passes((string) ($_POST['cf-turnstile-response'] ?? ''), $_SERVER['REMOTE_ADDR'] ?? '')) { return $this->go('/admin/login', 'Complete the security check and try again.'); }
                if ($this->auth->password((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''), $_SERVER['REMOTE_ADDR'] ?? '')) { return $this->go('/admin/posts', 'Welcome back.'); }
                $_SESSION['login_username'] = mb_substr(trim((string) ($_POST['username'] ?? '')), 0, 191);
                return $this->go('/admin/login', 'Unable to sign in. Check your credentials or try again later.');
            }
            $username = (string) ($_SESSION['login_username'] ?? ''); unset($_SESSION['login_username']);
            if (!$protection->active()) { return $this->render('login', 'Sign in', ['turnstileKey' => '', 'username' => $username]); }
            header('Content-Security-Policy: ' . security_policy("'self' " . \Core\Turnstile::ORIGIN, \Core\Turnstile::ORIGIN));
            return $this->render('login', 'Sign in', ['turnstileKey' => $protection->siteKey(), 'username' => $username]);
        }
        if ($path === '/admin/logout' && $post) { Session::logout(); return $this->go('/admin/login', 'Signed out.'); }
        if ($path === '/admin') { return $this->go('/admin/posts'); }
        if (str_starts_with($path, '/admin/media') || $path === '/admin/optimization') {
            $media = new MediaController($this->db, $this->render(...), $this->go(...), dirname(__DIR__, 3) . '/public');
            $result = $media->handle($path, $post);
            if ($result !== null) { return $result; }
        }
        if ($path === '/admin/updates/restore' && !$post) {
            $backup = (string) ($_GET['backup'] ?? '');
            $found = array_filter((new \App\Services\AdminUpdates($this->db, dirname(__DIR__, 3)))->data()['backups'], fn ($b) => $b['name'] === $backup);
            if (!$found) { http_response_code(404); return $this->render('message', 'Not found', ['message' => 'This backup no longer exists.']); }
            return $this->render('updates-restore', 'Restore backup', ['backup' => reset($found)]);
        }
        if (preg_match('~^/admin/security/passkeys/(\d+)/remove$~D', $path, $m) && !$post) {
            $key = $this->db->one('SELECT * FROM passkeys WHERE id = ? AND user_id = ?', [(int) $m[1], $user['id']]);
            if (!$key) { http_response_code(404); return $this->render('message', 'Not found', ['message' => 'This passkey no longer exists.']); }
            return $this->render('passkey-remove', 'Remove passkey', ['key' => $key, 'last' => (int) $this->db->one('SELECT COUNT(*) AS n FROM passkeys WHERE user_id = ?', [$user['id']])['n'] === 1]);
        }
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
            if ($post) { $editor->delete($m[1], (int) $m[2]); return $this->go('/admin/taxonomy' . ($m[1] === 'tag' ? '?tab=tags' : ''), ucfirst($m[1]) . ' “' . $term['slug'] . '” deleted.'); }
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
        if ($path === '/admin/languages' && $post && in_array($_POST['action'] ?? '', ['catalog', 'download'], true)) {
            try {
                $downloads = new LanguageDownloads($this->db, new \Core\GitHubUpdates(new \Core\CurlHttpClient()));
                if ($_POST['action'] === 'download') { $message = $downloads->install((string) ($_POST['code'] ?? '')); }
                else {
                    $downloads->check(); $states = array_count_values(array_column($downloads->rows(), 'state'));
                    $message = 'Language catalog checked: ' . ($states['new'] ?? 0) . ' to add, ' . ($states['update'] ?? 0) . ' with newer text.';
                }
            } catch (\InvalidArgumentException | \RuntimeException $e) { $message = $e->getMessage(); }
            return $this->go('/admin/languages#catalog', $message);
        }
        if ($path === '/admin/announcements' || str_starts_with($path, '/admin/announcements/')) { return $this->announcements($path, $post); }
        if ($path === '/admin/comments' || str_starts_with($path, '/admin/comments/')) {
            $result = (new CommentsController($this->db, $this->i18n, $this->render(...), $this->go(...), $user))->handle($path, $post);
            if ($result !== null) { return $result; }
        }
        $section = match ($path) { '/admin/languages' => 'languages', '/admin/menus' => 'menus', '/admin/settings' => 'settings', '/admin/strings' => 'strings', '/admin/taxonomy' => 'taxonomy', '/admin/security' => 'security',
            '/admin/home' => 'home', '/admin/author' => 'author', '/admin/appearance' => 'appearance', default => '' };
        if ($section !== '') {
            $error = ''; $old = $_SESSION['old'][$section] ?? []; unset($_SESSION['old'][$section]);
            if ($post) {
                try {
                    match ($section) {
                        'languages' => (new Languages($this->db))->save($_POST['action'] ?? 'add', $_POST),
                        'settings' => (new AdminSettings($this->db))->save($_POST),
                        'strings' => $this->saveStrings(),
                        'menus' => (new MenuEditor($this->db))->save((string) ($_POST['menu'] ?? ''), $_POST),
                        'taxonomy' => (new TaxonomyEditor($this->db))->save($_POST),
                        'security' => (new Account($this->db, $this->protection()))->update($user, $_POST['action'] ?? '', $_POST),
                        'home' => (new HomePage($this->db))->save($_POST),
                        'author' => (new Author($this->db))->save($_POST),
                        'appearance' => (new Appearance($this->db))->save($_POST),
                    };
                    return $this->go($path . $this->query($section), $section === 'security' && ($_POST['action'] ?? '') === 'remove' ? 'Passkey removed. Password sign-in remains available.' : 'Changes saved.');
                } catch (\InvalidArgumentException $e) {
                    $safe = $_POST; foreach (['current_password', 'new_password', 'new_password_confirm', 'turnstile_secret_key'] as $key) { unset($safe[$key]); }
                    $_SESSION['old'][$section] = $safe;
                    return $this->go($path . $this->query($section), $e->getMessage());
                }
            }
            $locale = (string) ($_GET['locale'] ?? $this->i18n->defaultLocale());
            if (!isset($this->i18n->registry()[$locale])) { $locale = $this->i18n->defaultLocale(); }
            $data = ['db' => $this->db, 'locale' => $locale, 'old' => $old, 'settings' => new \App\Repositories\Settings($this->db, $this->i18n)];
            if ($section === 'security') { $data['protection'] = $this->protection(); }
            if ($section === 'menus') {
                $menus = new \App\Repositories\Menus($this->db); $menus->ensureDefaults();
                $data['menus'] = ['header' => $menus->rows('header'), 'footer' => $menus->rows('footer')];
                $data['pages'] = [];
                foreach ($this->db->all('SELECT p.id, p.slug, p.base_locale, p.is_published, t.locale, t.title, t.status FROM pages p LEFT JOIN page_translations t ON t.page_id = p.id ORDER BY p.sort_order, p.id') as $row) {
                    $data['pages'][$row['id']] ??= ['id' => (int) $row['id'], 'slug' => $row['slug'], 'base' => $row['base_locale'], 'visible' => (bool) $row['is_published'], 'labels' => [], 'published' => []];
                    if ($row['locale'] !== null) {
                        $data['pages'][$row['id']]['labels'][$row['locale']] = $row['title'];
                        if ($row['status'] === 'published') { $data['pages'][$row['id']]['published'][] = $row['locale']; }
                    }
                }
            }
            return $this->render($section, ['taxonomy' => 'Categories & tags', 'menus' => 'Header & footer', 'strings' => 'Interface text', 'home' => 'Home page'][$section] ?? ucfirst($section), $data);
        }
        http_response_code(404); return $this->render('message', 'Not found', ['message' => 'This admin page does not exist.']);
    }
    private function announcements(string $path, bool $post): string
    {
        $repository = new \App\Repositories\Announcements($this->db); $editor = new \App\Services\AnnouncementEditor($this->db);
        if ($path === '/admin/announcements') {
            if ($post) {
                try { $editor->saveList($_POST); $message = 'Changes saved.'; } catch (\InvalidArgumentException $e) { $message = $e->getMessage(); }
                return $this->go('/admin/announcements', $message);
            }
            return $this->render('announcements', 'Announcements', ['rows' => $repository->all(), 'locales' => $this->i18n->registry()]);
        }
        $missing = function (): string { http_response_code(404); return $this->render('message', 'Not found', ['message' => 'This announcement no longer exists.']); };
        if (!preg_match('~^/admin/announcements/(new|[1-9]\d{0,9})(/delete)?$~D', $path, $m) || ($m[1] === 'new' && !empty($m[2]))) { return $missing(); }
        $id = $m[1] === 'new' ? 0 : (int) $m[1];
        $row = $id > 0 ? $repository->find($id) : null;
        if ($id > 0 && !$row) { return $missing(); }
        if (!empty($m[2])) {
            if ($post) { $editor->delete($id); return $this->go('/admin/announcements', 'Announcement deleted.'); }
            return $this->render('announcement-delete', 'Delete announcement', ['row' => $row, 'action' => $path]);
        }
        if ($post) {
            try { $editor->save($id, $_POST); return $this->go('/admin/announcements', 'Announcement saved.'); }
            catch (\InvalidArgumentException $e) { $_SESSION['old']['announcement'] = ['path' => $path, 'values' => $_POST]; return $this->go($path, $e->getMessage()); }
        }
        $old = $_SESSION['old']['announcement'] ?? []; unset($_SESSION['old']['announcement']);
        return $this->render('announcement', $id > 0 ? 'Edit announcement' : 'New announcement', ['row' => $row, 'action' => $path,
            'old' => ($old['path'] ?? '') === $path ? $old['values'] : [], 'locales' => $this->i18n->registry()]);
    }
    private function protection(): LoginProtection { return new LoginProtection($this->db, $this->config['turnstile'] ?? true); }
    /** Query string to return to after saving a section, so the admin lands on the same view. */
    private function query(string $section): string
    {
        $keep = match ($section) { 'taxonomy' => ['tab', 'q'], 'menus' => ['tab'], 'strings' => ['q', 'missing'], default => [] };
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
        $sorts = self::SORTS + ($page ? [] : ['published' => 'Publish date']);
        $sort = is_string($_GET['sort'] ?? null) && isset($sorts[$_GET['sort']]) ? $_GET['sort'] : 'updated';
        $order = match ($sort) { 'created' => 'created_at DESC, id DESC', 'oldest' => 'created_at ASC, id ASC',
            'published' => 'published_at IS NULL, published_at DESC, id DESC', default => 'updated_at DESC, id DESC' };
        $items = $this->db->all("SELECT * FROM $table ORDER BY $order");
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
        if ($sort === 'title' || $sort === 'title_desc') {
            // Titles in the base language, compared the way people read them (intl when available).
            $title = fn ($row) => (string) ($row['translations'][$row['base_locale']]['title'] ?? $row['slug']);
            $collator = class_exists(\Collator::class) ? new \Collator('root') : null;
            $collator?->setAttribute(\Collator::NUMERIC_COLLATION, \Collator::ON);
            usort($rows, fn ($a, $b) => ($sort === 'title' ? 1 : -1) * ($collator ? (int) $collator->compare($title($a), $title($b)) : strnatcasecmp($title($a), $title($b))));
        }
        $total = count($rows); $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $number = max(1, min((int) ($_GET['page'] ?? 1), $pages));
        return $this->render('matrix', $page ? 'Pages' : 'Posts', ['rows' => array_slice($rows, ($number - 1) * self::PER_PAGE, self::PER_PAGE), 'locales' => $locales,
            'pageContent' => $page, 'q' => $q, 'filter' => $filter, 'sort' => $sort, 'sorts' => $sorts, 'counts' => $counts, 'number' => $number, 'pages' => $pages, 'total' => $total]);
    }
    private function editor(bool $post): string
    {
        $page = ($_GET['kind'] ?? '') === 'page'; $id = (int) ($_GET['post'] ?? $_GET['id'] ?? 0);
        [$table, $translations, $foreign] = ContentEditor::tables($page);
        $locale = (string) ($_GET['locale'] ?? $this->i18n->defaultLocale());
        if (!isset($this->i18n->registry()[$locale])) { http_response_code(404); return $this->render('message', 'Not found', ['message' => 'Unknown language.']); }
        $item = $id ? $this->db->one("SELECT * FROM $table WHERE id = ?", [$id]) : ['base_locale' => $locale, 'is_published' => 1, 'comments_enabled' => 1];
        if (!$item) { http_response_code(404); return $this->render('message', 'Not found', ['message' => 'Content not found.']); }
        $target = '/admin/editor?' . http_build_query(['post' => $id, 'locale' => $locale, 'kind' => $page ? 'page' : 'post', 'from' => $_GET['from'] ?? $item['base_locale']]);
        if ($post) {
            try {
                $editor = new ContentEditor($this->db);
                $saved = $editor->save($page, $id, $locale, $_POST);
                if ($editor->published) {
                    $webmentions = new \App\Services\Webmentions($this->db, $this->config['app_url']);
                    if ($webmentions->queueOutgoing(...$editor->published) > 0) { \App\Services\Webmentions::later(static fn () => $webmentions->process(10)); }
                }
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
        if ($old && $old['target'] === $target) { $values = $old['values'] + ['is_pinned' => 0, 'is_published' => 0, 'markdown_extended' => 0, 'comments_enabled' => 0] + $values; $error = $old['error']; }
        $from = (string) ($_GET['from'] ?? $item['base_locale']);
        return $this->render('editor', $id ? 'Edit ' . ($page ? 'page' : 'post') : 'New ' . ($page ? 'page' : 'post'),
            compact('page', 'id', 'locale', 'item', 'values', 'error', 'target', 'ts', 'from') + ['commentsOn' => (new \App\Repositories\Settings($this->db, $this->i18n))->global('comments_enabled', '0') === '1', 'reference' => $ts[$from] ?? [], 'categories' => $this->db->all('SELECT * FROM categories ORDER BY sort_order, slug')]);
    }
}
