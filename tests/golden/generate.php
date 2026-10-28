<?php
/** Run manually BEFORE the Theme API refactor. Never regenerate to accept a rendering regression. */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/core/autoload.php';
function test(string $name, callable $body): void {}
function database(): Core\Database {
    $db = new Core\Database(['driver' => 'sqlite', 'path' => ':memory:']);
    $sql = preg_replace('/^\s*--.*$/m', '', file_get_contents(dirname(__DIR__, 2) . '/database/schema.sqlite.sql'));
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) { $db->query($statement); }
    return $db;
}
require dirname(__DIR__) . '/PublicTest.php';
require __DIR__ . '/fixture.php';
ob_start();
foreach (themeGoldenPages() as $name => $html) { file_put_contents(__DIR__ . '/' . $name . '.html', $html); }
ob_end_clean();
echo "Captured original public HTML.\n";
