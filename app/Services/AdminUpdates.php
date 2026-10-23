<?php
declare(strict_types=1);
namespace App\Services;
use Core\{Auth, Database, GitHubUpdates, CurlHttpClient, ReleasePackage, Updater};
final class AdminUpdates
{
    public function __construct(private Database $db, private string $root) {}
    public function data(): array
    {
        return ['repository' => GitHubUpdates::REPOSITORY, 'release' => $_SESSION['update_release'] ?? null,
            'backups' => array_map($this->backup(...), (new Updater($this->root, $this->db))->backups())];
    }
    /** Backup names look like meteor-<version>-<Ymd>-<His>-<microseconds>-<random>.zip. */
    private function backup(string $name): array
    {
        $file = $this->root . '/storage/backups/' . $name;
        preg_match('/^meteor-(\d+\.\d+\.\d+)-(\d{4})(\d{2})(\d{2})-(\d{2})(\d{2})(\d{2})-/', $name, $m);
        return ['name' => $name, 'version' => $m[1] ?? '', 'created' => $m ? "$m[2]-$m[3]-$m[4] $m[5]:$m[6]" : '', 'size' => is_file($file) ? (int) filesize($file) : 0];
    }
    public function submit(array $user, array $input, array $files): string
    {
        $action = $input['action'] ?? '';
        ReleasePackage::requirements();
        $github = new GitHubUpdates(new CurlHttpClient());
        if ($action === 'check') {
            $_SESSION['update_release'] = $github->latest();
            return 'Release information refreshed.';
        }
        $updater = new Updater($this->root, $this->db);
        // Restoring replaces the code with an older copy, so it alone asks for the password again.
        if ($action === 'restore') {
            $auth = new Auth($this->db); $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            if ($auth->limited($user['username'], $ip)) { throw new \RuntimeException('Too many password attempts. Try again in 15 minutes.'); }
            if (!password_verify($input['current_password'] ?? '', $user['password_hash'])) {
                $auth->failure($user['username'], $ip); throw new \InvalidArgumentException('Current password is incorrect.');
            }
            $updater->restore($input['backup'] ?? ''); return 'Code backup restored.';
        }
        $allow = isset($input['allow_downgrade']);
        if ($action === 'upload') {
            $file = $files['update'] ?? [];
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '') || ($file['size'] ?? 0) > ReleasePackage::LIMIT) { throw new \RuntimeException('Upload a zip up to 50 MB. Check PHP upload_max_filesize and post_max_size if the upload fails.'); }
            $updater->apply($file['tmp_name'], $allow, trim($input['sha256'] ?? ''));
        } elseif ($action === 'online') {
            $release = $github->latest();
            $destination = $this->root . '/storage/updates/download-' . bin2hex(random_bytes(12)) . '.zip';
            try {
                $checksum = $github->download($release, $destination);
                $updater->apply($destination, $allow, $checksum);
            } finally { if (is_file($destination)) { unlink($destination); } }
        } else { throw new \InvalidArgumentException('Unknown update action.'); }
        unset($_SESSION['update_release']); return 'Update installed successfully.';
    }
}
