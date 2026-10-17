<?php
test('Helpers: escaping, Unicode slugs, URL and CSRF', function () {
    same('&lt;&quot;&#039;&amp;&gt;', e('<"\'&>'));
    same('中文-hello', slugify('中文 Hello!'));
    same('section', slugify('!'));
    $GLOBALS['config'] = ['app_url' => 'https://example.test'];
    same('https://example.test/en/', url('/en/'));
    $_SESSION = [];
    ok(!Core\Csrf::verify('')); $token = Core\Csrf::token(); same(64, strlen($token));
    same($token, Core\Csrf::token()); ok(Core\Csrf::verify($token));
    ok(!Core\Csrf::verify([])); ok(!Core\Csrf::verify('wrong'));
    throws(fn () => Core\Csrf::requireValid('wrong'));
    contains(e($token), Core\Csrf::field());
    throws(fn () => redirect('//example.test')); throws(fn () => redirect("/test\r\nInjected: yes"));
});
test('View: placeholder layout escapes UI values and emits no scripts', function () {
    $db = database(); locale($db);
    $db->upsert('ui_strings', ['locale' => 'en', 'key' => 'placeholder', 'value' => '<script>bad</script>'], ['locale', 'key']);
    $i18n = new Core\I18n($db, require dirname(__DIR__) . '/lang/en.php');
    $GLOBALS['i18n'] = $i18n;
    $view = new Core\View(dirname(__DIR__) . '/views');
    $html = $view->render('public/home', compact('i18n'), 'layouts/public');
    contains('<html lang="en" dir="ltr">', $html);
    contains('&lt;script&gt;bad&lt;/script&gt;', $html);
    ok(!str_contains($html, '<script'));
    throws(fn () => $view->partial('../config/config'));
    throws(fn () => $view->partial('missing'));
});
