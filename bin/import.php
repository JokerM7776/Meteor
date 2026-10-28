#!/usr/bin/env php
<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/core/autoload.php';
$usage = <<<'TEXT'
Usage:
  php bin/import.php hexo <folder-or-zip> [--locale=en] [--rename] [--drafts] [--check]
  php bin/import.php typecho --driver=mysql --host=127.0.0.1 --port=3306 --database=typecho --user=typecho [--password=…]
  php bin/import.php typecho --driver=sqlite --file=<path.db>
Typecho options: --prefix=typecho_ --uploads=<folder-or-zip> --site=https://old.example.com
Shared options: --locale=auto|<code> --rename --drafts --check
The default language is automatic: Hexo config language when available, otherwise the site default.
Omit a MySQL password to enter it without echo on a terminal (or provide it on STDIN).
TEXT;
if (in_array('--help', $argv, true) || count($argv) < 2) { echo $usage . "\n"; exit(0); }
try {
    $type = $argv[1]; $input = []; $options = [];
    foreach (array_slice($argv, 2) as $arg) {
        if (!str_starts_with($arg, '--')) { if ($type !== 'hexo' || isset($input['file'])) { throw new InvalidArgumentException('Unexpected argument.'); } $input['file'] = $arg; continue; }
        [$key, $value] = array_pad(explode('=', substr($arg, 2), 2), 2, null);
        if (in_array($key, ['check', 'drafts', 'rename'], true)) { if ($value !== null) { throw new InvalidArgumentException('Use --' . $key . ' without a value.'); } $options[$key === 'rename' ? 'conflict' : $key] = $key === 'rename' ? 'rename' : true; }
        elseif ($key === 'locale') { $options['locale'] = $value; }
        elseif (in_array($key, ['driver', 'host', 'port', 'database', 'user', 'password', 'prefix', 'uploads', 'site', 'file'], true) && $value !== null) { $input[$key] = $value; }
        else { throw new InvalidArgumentException('Unknown option: --' . $key); }
    }
    if ($type === 'hexo' && empty($input['file'])) { throw new InvalidArgumentException('Supply a Hexo folder or zip.'); }
    if ($type === 'typecho' && ($input['driver'] ?? 'mysql') === 'mysql' && !isset($input['password'])) {
        $terminal = stream_isatty(STDIN); $state = null;
        if ($terminal) {
            if (!function_exists('shell_exec')) { throw new RuntimeException('Cannot disable terminal echo; pipe the password on STDIN.'); }
            $state = trim((string) shell_exec('stty -g')); if (!preg_match('/^[a-zA-Z0-9:;]+$/D', $state)) { throw new RuntimeException('Cannot disable terminal echo; pipe the password on STDIN.'); }
            if (shell_exec('stty -echo && printf disabled') !== 'disabled') { throw new RuntimeException('Cannot disable terminal echo; pipe the password on STDIN.'); }
            fwrite(STDERR, 'Typecho password: ');
        }
        try { $input['password'] = rtrim((string) fgets(STDIN), "\r\n"); }
        finally { if ($state !== null) { shell_exec('stty ' . escapeshellarg($state)); fwrite(STDERR, "\n"); } }
    }
    $config = require dirname(__DIR__) . '/config/config.php'; $db = new Core\Database($config['database']);
    $report = (new App\Services\Import\Importer($db))->run($type, $input, $options);
    unset($input['password']); echo $report->text(); exit($report->failed() ? 1 : 0);
} catch (Throwable $error) { fwrite(STDERR, 'Import failed: ' . ($error instanceof PDOException ? 'Cannot open the Meteor database.' : $error->getMessage()) . "\n"); exit(1); }
