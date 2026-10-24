<?php
// An online update replaces the files, then runs the migrations and LanguagePacks::sync() in the
// same request, next to classes the old version already loaded. Run that step in a fresh process
// whose Core\LanguagePack is the 0.1.x one.
test('Upgrade: LanguagePacks keeps its own copy of the renamed language codes', function () {
    same(Core\LanguagePack::RENAMED, App\Services\LanguagePacks::RENAMED);
});
test('Upgrade: migrations and language sync run next to the 0.1.x LanguagePack class', function () {
    $root = dirname(__DIR__);
    $db = $root . '/storage/test-upgrade-' . bin2hex(random_bytes(6)) . '.sqlite';
    $script = '<?php
        require ' . var_export(__DIR__ . '/legacy/LanguagePack-0.1.php', true) . ';
        require ' . var_export($root . '/core/autoload.php', true) . ';
        $db = new Core\Database(["driver" => "sqlite", "path" => ' . var_export($db, true) . ']);
        $sql = preg_replace("/^\s*--.*$/m", "", file_get_contents(' . var_export($root . '/database/schema.sqlite.sql', true) . '));
        foreach (array_filter(array_map("trim", explode(";", $sql))) as $statement) { $db->query($statement); }
        $db->query("INSERT INTO locales (code, name, native_name, status, is_default) VALUES (\'en\', \'English\', \'English\', \'live\', 1), (\'zh\', \'Chinese\', \'中文\', \'live\', 0)");
        App\Services\LanguagePacks::remember($db, ["ms", "zh"]);
        (new Core\Migrator($db, ' . var_export($root . '/database/migrations', true) . '))->run();
        $added = App\Services\LanguagePacks::sync($db, ' . var_export($root . '/lang', true) . ');
        echo json_encode(["added" => $added, "codes" => array_column($db->all("SELECT code FROM locales ORDER BY code"), "code"), "legacy" => !method_exists(Core\LanguagePack::class, "fileCode")]);';
    $file = $root . '/storage/test-upgrade-' . bin2hex(random_bytes(6)) . '.php';
    file_put_contents($file, $script);
    try {
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $output, $status);
        ok($status === 0, implode("\n", $output));
        same(['added' => [], 'codes' => ['en', 'zh'], 'legacy' => true], json_decode(implode('', $output), true));
    } finally { @unlink($file); @unlink($db); }
});
