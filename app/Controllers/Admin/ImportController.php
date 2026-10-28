<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Services\Import\{CheckedFiles, Files, Importer};
use Core\Database;

final class ImportController
{
    public function __construct(private Database $db, private \Closure $render) {}
    public static function uploadLimit(): int
    {
        $limits = [Files::MAX_TOTAL];
        foreach (['upload_max_filesize', 'post_max_size'] as $key) { $n = \Core\ImageOptimizer::bytes((string) ini_get($key)); if ($n > 0) { $limits[] = $n; } }
        return min($limits);
    }
    private function upload(string $key, bool $optional = false): ?string
    {
        $file = $_FILES[$key] ?? []; $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE && $optional) { return null; }
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || ($file['size'] ?? 0) > self::uploadLimit()) { throw new \InvalidArgumentException('Upload exceeds this server’s limit. Use the CLI for large blogs.'); }
        if ($error === UPLOAD_ERR_NO_FILE) { throw new \InvalidArgumentException('Choose a file to upload.'); }
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) { throw new \InvalidArgumentException('The upload failed. Please try again.'); }
        return $file['tmp_name'];
    }
    public function handle(bool $post): string
    {
        $source = ($_GET['source'] ?? '') === 'typecho' ? 'typecho' : 'hexo'; $report = null; $error = '';
        $files = new CheckedFiles(dirname(__DIR__, 3) . '/storage/imports'); $files->prune();
        if (isset($_SESSION['import']) && !$files->exists($_SESSION['import']['token'])) { unset($_SESSION['import']); }
        $old = CheckedFiles::options($_POST); $input = []; $temporary = null; $keep = false;
        if ($post) {
            try {
                @set_time_limit(0); $action = $_POST['action'] ?? 'check';
                if (in_array($action, ['import_checked', 'cancel'], true)) {
                    $saved = $_SESSION['import'] ?? null; $token = $_POST['token'] ?? null;
                    if (!$saved || !is_string($token) || !hash_equals($saved['token'], $token)) {
                        throw new \InvalidArgumentException('This checked import is no longer available. Upload the files and Check again.');
                    }
                    $source = $saved['source']; $old = $saved['options'];
                    $temporary = $token; unset($_SESSION['import']);
                    if ($action === 'import_checked') {
                        $input = $files->input($token, $source, $old);
                        $input['password'] = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
                        if ($source === 'typecho' && ($old['driver'] ?? 'mysql') === 'mysql' && $input['password'] === '') { throw new \InvalidArgumentException('Enter the Typecho database password again.'); }
                        $report = $this->run($source, $input, $old, false);
                    }
                } else {
                    $uploads = $source === 'hexo' ? ['archive' => $this->upload('archive')] : ['uploads' => $this->upload('uploads', true)];
                    if ($source === 'typecho' && ($old['driver'] ?? 'mysql') === 'sqlite') { $uploads['sqlite'] = $this->upload('sqlite'); }
                    $temporary = $files->create($uploads);
                    $input = $files->input($temporary, $source, $old);
                    $input['password'] = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
                    $report = $this->run($source, $input, $old, $action !== 'import');
                    if ($report->check && !$report->failed()) {
                        if (isset($_SESSION['import'])) { $files->remove($_SESSION['import']['token']); }
                        $_SESSION['import'] = ['token' => $temporary, 'source' => $source, 'options' => $old]; $keep = true;
                    }
                }
            } catch (\Throwable $e) { $error = $e instanceof \PDOException ? 'Could not read the source database.' : $e->getMessage(); }
            finally { unset($input['password']); if ($temporary !== null && !$keep) { $files->remove($temporary); } }
        }
        return ($this->render)('import', 'Import', ['source' => $source, 'report' => $report, 'error' => $error, 'old' => $old,
            'checked' => $report?->check && !$report->failed() ? ($_SESSION['import'] ?? null) : null,
            'locales' => $this->db->all('SELECT * FROM locales ORDER BY sort_order, code'), 'uploadLimit' => self::uploadLimit()]);
    }
    private function run(string $source, array $input, array $options, bool $check): \App\Services\Import\Report
    {
        return (new Importer($this->db))->run($source, $input, ['locale' => $options['locale'] ?? 'auto', 'conflict' => $options['conflict'] ?? 'skip',
            'drafts' => isset($options['drafts']), 'check' => $check]);
    }
}
