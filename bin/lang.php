<?php
declare(strict_types=1);
// Tools for translators and maintainers of lang/*.json. Run `php bin/lang.php help`.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/core/autoload.php';
use Core\{LanguageCatalog, LanguagePack};

$directory = LanguagePack::directory();
$builtins = require $directory . '/en.php';
$command = $argv[1] ?? 'help';
$indexFile = $directory . '/' . LanguageCatalog::FILE;
$files = static fn (): array => array_values(array_filter(glob($directory . '/*.json') ?: [], fn ($file) => basename($file) !== LanguageCatalog::FILE));

/** Problems with one language file: [errors, warnings]. */
function review(string $file, array $builtins): array
{
    $errors = []; $warnings = [];
    $code = basename($file, '.json');
    $json = (string) file_get_contents($file);
    try { $pack = LanguagePack::parse($json, $builtins); }
    catch (InvalidArgumentException $e) { return [[$e->getMessage()], []]; }
    if ($pack['code'] !== $code) { $errors[] = 'The file name must match its code: rename it to ' . $pack['code'] . '.json or set "code" to "' . $code . '".'; }
    if (isset(LanguagePack::RENAMED[$code])) { $errors[] = 'Use the code ' . LanguagePack::RENAMED[$code] . ' instead of ' . $code . '.'; }
    $strings = json_decode($json, true)['strings'];
    foreach ($strings as $key => $text) {
        if (!isset($builtins[$key])) { $errors[] = 'Unknown key "' . $key . '". Keys come from lang/en.php.'; continue; }
        if (!is_string($text)) { $errors[] = $key . ': the text must be a string.'; continue; }
        if (trim($text) === '') { continue; }
        if (!LanguagePack::validText(trim($text), $builtins[$key])) {
            $tokens = LanguagePack::placeholders($builtins[$key]);
            $errors[] = $key . ': ' . ($tokens ? 'keep exactly the placeholders ' . implode(' ', $tokens) : 'remove the {braces}') . ', no control characters, up to ' . LanguagePack::MAX_TEXT . ' characters.';
        }
    }
    $missing = array_keys(array_diff_key($builtins, $pack['strings']));
    if ($missing) { $warnings[] = count($missing) . ' not translated yet (English is shown): ' . implode(', ', $missing); }
    $english = array_keys(array_intersect_assoc($pack['strings'], $builtins));
    if ($english) { $warnings[] = count($english) . ' still the same as English (fine if intended): ' . implode(', ', $english); }
    return [$errors, $warnings];
}

switch ($command) {
    case 'check':
        $failed = false;
        foreach ($files() as $file) {
            [$errors, $warnings] = review($file, $builtins);
            $pack = LanguagePack::all($builtins, $directory)[basename($file, '.json')] ?? null;
            echo basename($file) . ($pack ? ' — ' . count($pack['strings']) . ' / ' . count($builtins) . ' texts' : '') . "\n";
            foreach ($errors as $error) { echo "  ERROR  $error\n"; }
            foreach ($warnings as $warning) { echo "  note   $warning\n"; }
            $failed = $failed || $errors;
        }
        $current = is_file($indexFile) ? (string) file_get_contents($indexFile) : '';
        $previous = $current !== '' ? LanguageCatalog::parse($current) : [];
        $fresh = LanguageCatalog::build($directory, $builtins, $previous);
        if (array_map(fn ($e) => $e['sha256'], $fresh) !== array_map(fn ($e) => $e['sha256'], $previous)) {
            echo "\nlang/index.json is out of date. It is rebuilt automatically after merging; to update it yourself, run: php bin/lang.php index\n";
        }
        echo $failed ? "\nFix the errors above.\n" : "\nAll language files are valid.\n";
        exit($failed ? 1 : 0);
    case 'index':
        $previous = is_file($indexFile) ? LanguageCatalog::parse((string) file_get_contents($indexFile)) : [];
        $json = LanguageCatalog::encode(LanguageCatalog::build($directory, $builtins, $previous));
        if ($json === @file_get_contents($indexFile)) { echo "lang/index.json is up to date.\n"; exit(0); }
        file_put_contents($indexFile, $json);
        echo "lang/index.json updated.\n";
        exit(0);
    case 'new':
        [$code, $name, $native] = [$argv[2] ?? '', $argv[3] ?? '', $argv[4] ?? ''];
        if (!LanguagePack::validCode($code) || trim($name) === '' || trim($native) === '') {
            fwrite(STDERR, "Usage: php bin/lang.php new <code> \"<name in English>\" \"<name in the language>\" [--rtl]\nExample: php bin/lang.php new ja Japanese 日本語\n"); exit(1);
        }
        if (isset(LanguagePack::RENAMED[$code])) { fwrite(STDERR, 'Use the code ' . LanguagePack::RENAMED[$code] . " instead.\n"); exit(1); }
        $file = $directory . '/' . $code . '.json';
        if (is_file($file)) { fwrite(STDERR, "lang/$code.json already exists. Edit it instead.\n"); exit(1); }
        $locale = ['code' => $code, 'name' => trim($name), 'native_name' => trim($native), 'direction' => in_array('--rtl', $argv, true) ? 'rtl' : 'ltr'];
        file_put_contents($file, LanguagePack::encode($locale, $builtins));
        echo "Created lang/$code.json with the English text. Translate every value, keep {placeholders}, then run: php bin/lang.php check\n";
        exit(0);
    default:
        echo "php bin/lang.php check                         Validate every lang/*.json file\n";
        echo "php bin/lang.php new <code> <name> <native>    Start a language file from the English text (--rtl for right to left)\n";
        echo "php bin/lang.php index                         Rebuild lang/index.json, the catalog that sites download from\n";
        exit($command === 'help' ? 0 : 1);
}
