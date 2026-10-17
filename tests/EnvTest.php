<?php
test('Env: quotes, comments, exports, empties and no interpolation', function () {
    $parsed = Core\Env::parse(<<<'ENV'
# comment
 APP_ENV = development # comment
export DB_PASS="a # secret"
SINGLE='literal\n'
DOUBLE="line\nnext\t\"quoted\""
EMPTY=
HASH=value#fragment
LITERAL=${HOME}
DUP=old
DUP=new
ENV);
    same('development', $parsed['APP_ENV']);
    same('a # secret', $parsed['DB_PASS']);
    same('literal\\n', $parsed['SINGLE']);
    same("line\nnext\t\"quoted\"", $parsed['DOUBLE']);
    same('', $parsed['EMPTY']); same('value#fragment', $parsed['HASH']);
    same('${HOME}', $parsed['LITERAL']); same('new', $parsed['DUP']);
    same([], Core\Env::load(__DIR__ . '/missing.env'));
    throws(fn () => Core\Env::parse('BAD LINE'), InvalidArgumentException::class);
    throws(fn () => Core\Env::parse('X="unclosed'), InvalidArgumentException::class);
});
test('Config: environment override and SQLite path resolution', function () {
    $saved = [getenv('DB_DRIVER'), getenv('DB_PATH')];
    try {
        putenv('DB_DRIVER=sqlite'); putenv('DB_PATH=storage/test.sqlite');
        $config = require dirname(__DIR__) . '/config/config.php';
        same('sqlite', $config['database']['driver']);
        same(dirname(__DIR__) . '/storage/test.sqlite', $config['database']['path']);
        putenv('DB_DRIVER=invalid');
        throws(fn () => require dirname(__DIR__) . '/config/config.php', InvalidArgumentException::class);
    } finally {
        foreach (['DB_DRIVER', 'DB_PATH'] as $i => $key) { putenv($saved[$i] === false ? $key : $key . '=' . $saved[$i]); }
    }
});
