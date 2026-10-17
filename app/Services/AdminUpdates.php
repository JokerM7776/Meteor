<?php
declare(strict_types=1);
namespace App\Services;
use Core\{Auth, Database, GitHubUpdates, CurlHttpClient, ReleasePackage, Updater};
final class AdminUpdates
{
    public function __construct(private Database $db, private string $root) {}
    private function setting(string $key): string
    {
        return $this->db->one('SELECT value FROM settings WHERE `key` = ? AND locale_scope = ?', [$key, ''])['value'] ?? '';
    }
    public function data(): array
    {
        return ['repository' => $this->setting('update_repository'), 'hasToken' => $this->setting('update_token') !== '',
            'release' => $_SESSION['update_release'] ?? null, 'backups' => (new Updater($this->root, $this->db))->backups()];
    }
    public function submit(array $user, array $input, array $files): string
    {
        $action = $input['action'] ?? '';
        if ($action === 'settings') {
            $repo = trim($input['repository'] ?? '');
            if ($repo !== '' && !preg_match('~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~D', $repo)) { throw new \InvalidArgumentException('Use owner/name for the GitHub repository.'); }
            $token = trim($input['token'] ?? '');
            if (preg_match('/[\r\n]/', $token)) { throw new \InvalidArgumentException('Invalid GitHub token.'); }
            $this->db->upsert('settings', ['key' => 'update_repository', 'locale' => null, 'value' => $repo], ['key', 'locale_scope']);
            if ($token !== '' || isset($input['clear_token'])) { $this->db->upsert('settings', ['key' => 'update_token', 'locale' => null, 'value' => isset($input['clear_token']) ? '' : $token], ['key', 'locale_scope']); }
            unset($_SESSION['update_release']); return 'Update settings saved.';
        }
        ReleasePackage::requirements();
        $github = new GitHubUpdates(new CurlHttpClient());
        if ($action === 'check') {
            $_SESSION['update_release'] = $github->latest($this->setting('update_repository'), $this->setting('update_token'));
            return 'Release information refreshed.';
        }
        $auth = new Auth($this->db); $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($auth->limited($user['username'], $ip)) { throw new \RuntimeException('Too many password attempts. Try again in 15 minutes.'); }
        if (!password_verify($input['current_password'] ?? '', $user['password_hash'])) {
            $auth->failure($user['username'], $ip); throw new \InvalidArgumentException('Current password is incorrect.');
        }
        $updater = new Updater($this->root, $this->db);
        if ($action === 'restore') { $updater->restore($input['backup'] ?? ''); return 'Code backup restored.'; }
        $allow = isset($input['allow_downgrade']);
        if ($action === 'upload') {
            $file = $files['update'] ?? [];
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '') || ($file['size'] ?? 0) > ReleasePackage::LIMIT) { throw new \RuntimeException('Upload a zip up to 50 MB. Check PHP upload_max_filesize and post_max_size if the upload fails.'); }
            $updater->apply($file['tmp_name'], $allow, trim($input['sha256'] ?? ''));
        } elseif ($action === 'online') {
            $release = $github->latest($this->setting('update_repository'), $this->setting('update_token'));
            $destination = $this->root . '/storage/updates/download-' . bin2hex(random_bytes(12)) . '.zip';
            try {
                $checksum = $github->download($release, $destination, $this->setting('update_token'));
                $updater->apply($destination, $allow, $checksum);
            } finally { if (is_file($destination)) { unlink($destination); } }
        } else { throw new \InvalidArgumentException('Unknown update action.'); }
        unset($_SESSION['update_release']); return 'Update installed successfully.';
    }
}
