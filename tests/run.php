<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/autoload.php';
require_once __DIR__ . '/fixtures.php';
ob_start();
$tests = [];
function test(string $name, callable $body): void { $GLOBALS['tests'][$name] = $body; }
function same(mixed $expected, mixed $actual): void {
    if ($expected !== $actual) { throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)); }
}
function ok(bool $condition, string $message = 'Assertion failed'): void {
    if (!$condition) { throw new RuntimeException($message); }
}
function contains(string $needle, string $haystack): void { ok(str_contains($haystack, $needle), 'Missing: ' . $needle); }
function throws(callable $body, string $class = Throwable::class): void {
    try { $body(); } catch (Throwable $error) { ok($error instanceof $class, get_class($error)); return; }
    throw new RuntimeException('Expected exception: ' . $class);
}
function database(): Core\Database {
    $db = new Core\Database(['driver' => 'sqlite', 'path' => ':memory:']);
    foreach (schemaStatements('sqlite') as $statement) { $db->query($statement); }
    return $db;
}
function schemaStatements(string $driver): array {
    $sql = file_get_contents(dirname(__DIR__) . '/database/schema.' . $driver . '.sql');
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    return array_values(array_filter(array_map('trim', explode(';', $sql))));
}
function locale(Core\Database $db, string $code = 'en', string $status = 'live', int $default = 1, int $order = 0): void {
    $db->query('INSERT INTO locales (code, name, native_name, status, is_default, sort_order) VALUES (?, ?, ?, ?, ?, ?)', [$code, $code, $code, $status, $default, $order]);
}
foreach (glob(__DIR__ . '/*Test.php') as $file) { require $file; }
$failed = 0;
foreach ($tests as $name => $body) {
    try { $body(); echo "PASS $name\n"; }
    catch (Throwable $error) { $failed++; fwrite(STDERR, "FAIL $name: {$error->getMessage()}\n{$error->getTraceAsString()}\n"); }
}
echo count($tests) . ' tests, ' . $failed . " failures\n";
exit($failed ? 1 : 0);
