<?php
require_once __DIR__ . '/golden/fixture.php';

test('Theme: original public HTML remains byte-identical', function () {
    foreach (themeGoldenPages() as $name => $actual) {
        $path = __DIR__ . '/golden/' . $name . '.html';
        ok(is_file($path), 'Missing golden: ' . $name);
        $expected = file_get_contents($path);
        if ($expected === $actual) { continue; }
        $before = explode("\n", $expected); $after = explode("\n", $actual);
        $at = 0;
        while (isset($before[$at], $after[$at]) && $before[$at] === $after[$at]) { $at++; }
        $diff = "--- $name (golden)\n+++ $name (current)\n@@ line " . ($at + 1) . " @@\n";
        foreach (array_slice($before, max(0, $at - 1), 4) as $line) { $diff .= '-' . $line . "\n"; }
        foreach (array_slice($after, max(0, $at - 1), 4) as $line) { $diff .= '+' . $line . "\n"; }
        throw new RuntimeException($diff);
    }
});
