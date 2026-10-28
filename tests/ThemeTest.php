<?php
use App\Services\Themes;
use App\Theme\Deprecated;

/** Runs $body with a temporary themes folder and log file; $write(slug, [path => contents]) adds a theme. */
function themeSandbox(callable $body): void
{
    $root = dirname(__DIR__) . '/storage/test-themes-' . bin2hex(random_bytes(6));
    mkdir($root . '/themes', 0700, true);
    [Themes::$directory, Deprecated::$logFile] = [$root . '/themes', $root . '/themes.log'];
    $write = function (string $slug, array $files) use ($root): void {
        foreach ($files as $path => $contents) {
            $file = $root . '/themes/' . $slug . '/' . $path;
            if (!is_dir(dirname($file))) { mkdir(dirname($file), 0700, true); }
            file_put_contents($file, is_array($contents) ? json_encode($contents) : $contents);
        }
    };
    try { $body($root, $write); }
    finally { [Themes::$directory, Deprecated::$logFile] = [null, null]; unset($_SESSION['theme_preview']); Core\ReleasePackage::removeTree($root); }
}
function themeJson(string $slug, array $extra = []): array { return $extra + ['name' => ucfirst($slug), 'slug' => $slug, 'version' => '1.0.0', 'api' => 1]; }
function installStarter(Core\Database $db): void
{
    (new Themes($db))->installStarter();
    (new Themes($db))->activate('starter');
}

test('Theme API: public templates use only $site, $page, $theme and the helpers', function () {
    $root = dirname(__DIR__) . '/views';
    $files = [$root . '/layouts/public.php', ...glob($root . '/public/*.php'), ...glob($root . '/partials/*.php')];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/examples', FilesystemIterator::SKIP_DOTS)) as $file) {
        if (str_ends_with($file->getFilename(), '.php')) { $files[] = $file->getPathname(); }
    }
    ok(count($files) > 20, 'Templates found');
    foreach ($files as $file) {
        $code = file_get_contents($file);
        ok(!preg_match('/\b(require|include)(_once)?\b\s*[(\'"_A-Z$]/', $code), basename($file) . ' includes files directly');
        foreach (['$settings', '$i18n', '$registry', '$view', '$db', '__DIR__', '$_GET', '$_POST', '$_SERVER', '$_COOKIE', '$_SESSION', '->enabled(', '->global(', '__('] as $name) {
            ok(!str_contains($code, $name), basename(dirname($file)) . '/' . basename($file) . ' uses ' . $name);
        }
    }
});

test('Theme API: every public property is documented in docs/THEMES.md', function () {
    $docs = file_get_contents(dirname(__DIR__) . '/docs/THEMES.md');
    foreach (glob(dirname(__DIR__) . '/app/Theme/*.php') as $file) {
        $class = 'App\\Theme\\' . basename($file, '.php');
        if (in_array(basename($file, '.php'), ['Builder', 'Context', 'Deprecated'], true)) { continue; }
        $short = basename($file, '.php');
        ok(preg_match('/^### ' . $short . '\b.*?(?=^### |\z)/ms', $docs, $section) === 1, 'Missing docs section: ' . $short);
        foreach ((new ReflectionClass($class))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            contains('`' . $property->getName() . '`', $section[0]);
        }
        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getName() !== '__construct') { contains('`' . $method->getName() . '(', $section[0]); }
        }
    }
    foreach (['head', 'body_start', 'header_after', 'main_before', 'main_after', 'home_before', 'home_after', 'post_before', 'post_after', 'archive_before', 'archive_after', 'footer_before', 'footer', 'body_end'] as $slot) {
        contains('`' . $slot . '`', $docs);
        $templates = implode('', array_map('file_get_contents', [dirname(__DIR__) . '/views/layouts/public.php', ...glob(dirname(__DIR__) . '/views/public/*.php')]));
        contains("slot('" . $slot . "')", $templates);
    }
});

test('Theme API: theme.json validation, problems and forward-compatible keys', function () {
    themeSandbox(function (string $root, callable $write) {
        $db = database(); locale($db); $themes = new Themes($db);
        $write('good', ['theme.json' => themeJson('good', ['future_key' => ['x' => 1], 'settings' => [
            ['key' => 'dark_hero', 'type' => 'toggle', 'label' => ['en' => 'Dark hero', 'zh-hans' => '深色'], 'default' => true],
            ['key' => 'layout', 'type' => 'select', 'label' => 'Layout', 'options' => ['wide' => 'Wide', 'narrow' => ['en' => 'Narrow']]],
            ['key' => 'note', 'type' => 'text', 'label' => 'Note', 'max' => 20],
            ['key' => 'accent', 'type' => 'color', 'label' => 'Accent', 'default' => '#112233']]])]);
        $write('BadSlug', ['theme.json' => themeJson('BadSlug')]);
        $write('future', ['theme.json' => themeJson('future', ['api' => 2])]);
        $write('newer', ['theme.json' => themeJson('newer', ['requires' => '99.0.0'])]);
        $write('mismatch', ['theme.json' => themeJson('other')]);
        $write('broken', ['theme.json' => '{not json']);
        $write('nofile', ['public/home.php' => 'x']);
        $write('badtype', ['theme.json' => themeJson('badtype', ['settings' => [['key' => 'x', 'type' => 'range', 'label' => 'X']]])]);
        $write('baddefault', ['theme.json' => themeJson('baddefault', ['settings' => [['key' => 'x', 'type' => 'color', 'label' => 'X', 'default' => 'red']]])]);
        $write('dupe', ['theme.json' => themeJson('dupe', ['settings' => [['key' => 'x', 'type' => 'text', 'label' => 'X'], ['key' => 'x', 'type' => 'text', 'label' => 'Y']]])]);
        $problems = array_column($themes->all(), 'problem', 'slug');
        same('', $problems['good']);
        contains('lowercase', $problems['BadSlug']); contains('Theme API 2', $problems['future']); contains('Needs Meteor 99.0.0', $problems['newer']);
        contains('match the folder', $problems['mismatch']); contains('not a JSON object', $problems['broken']); contains('theme.json is missing', $problems['nofile']);
        contains('toggle, select, text or color', $problems['badtype']); contains('default', $problems['baddefault']); contains('unique key', $problems['dupe']);
        $good = $themes->find('good');
        same(['dark_hero' => true, 'layout' => 'wide', 'note' => '', 'accent' => '#112233'], $themes->values($good));
        same('深色', Themes::label($good['settings'][0]['label'], 'zh-hans')); same('Dark hero', Themes::label($good['settings'][0]['label'], 'ms'));
        throws(fn () => $themes->activate('future'), InvalidArgumentException::class);
        throws(fn () => $themes->activate('missing'), InvalidArgumentException::class);
        same(['layout' => 'Choose one of the options.', 'note' => 'Use at most 20 characters.', 'accent' => 'Use a color like #ea580c.'],
            $themes->saveValues($good, ['layout' => 'huge', 'note' => str_repeat('x', 21), 'accent' => 'blue']));
        same(['dark_hero' => true, 'layout' => 'wide', 'note' => '', 'accent' => '#112233'], $themes->values($good), 'Nothing saved on errors');
        same([], $themes->saveValues($good, ['layout' => 'narrow', 'note' => 'Hi', 'accent' => '#AABBCC']));
        same(['dark_hero' => false, 'layout' => 'narrow', 'note' => 'Hi', 'accent' => '#aabbcc'], $themes->values($good));
        $object = $themes->object($good);
        same(':root{--theme-accent:#aabbcc}', $object->settingsCss()); contains('/theme-settings.css?v=', $object->settingsCssUrl());
        same(false, $object->setting('dark_hero')); same(null, $object->setting('unknown'));
        $db->query("UPDATE settings SET value = '\"gone\"' WHERE `key` = 'theme.good.layout'");
        same('wide', $themes->values($good)['layout'], 'A stored value that is no longer valid falls back to the default');
        same('', (new App\Theme\Theme())->settingsCss()); throws(fn () => (new App\Theme\Theme())->asset('x.css'), LogicException::class);
    });
});

test('Theme API: the starter theme renders every page type, overrides, slots, CSS and fallbacks', function () {
    themeSandbox(function (string $root, callable $write) {
        $app = publicApp(); installStarter($app['db']);
        foreach (['/en/', '/zh-hans/', '/en/posts', '/en/categories/engineering', '/en/tags/php', '/en/tags', '/en/posts?page=2',
            '/en/posts/building-a-zero-js-blog', '/en/about', '/en/search?q=PHP', '/zh-hans/no/such/page'] as $path) {
            $_SERVER['REQUEST_URI'] = $path;
            $html = publicRequest($app, $path);
            ok(!str_contains($html, '<script'), $path . ' has a script');
            contains('/themes/starter/theme.css?v=', $html); contains('/theme-settings.css?v=', $html);
            contains('Starter theme 1.0.0', $html);
        }
        $_SERVER['REQUEST_URI'] = '/en/';
        $home = publicRequest($app, '/en/'); contains('starter-hero', $home); contains('starter-grid--sidebar', $home); ok(!str_contains($home, 'max-w-[16ch]'), 'Theme home replaces the built-in one');
        $post = publicRequest($app, '/en/posts/building-a-zero-js-blog'); contains('starter-note', $post); contains('https://meteor.example/en/rss.xml', $post);
        same('', file_exists($root . '/themes.log') ? file_get_contents($root . '/themes.log') : '', 'No theme errors');
        same(':root{--theme-accent:#0f766e}', $app['feeds']->themeSettings());

        // A template with a syntax error, and a partial that calls an undefined function: built-in output, logged.
        $write('starter', ['public/home.php' => '<?php if ( ?>', 'partials/nav.php' => '<?php undefined_theme_function(); ?>', 'slots/head.php' => '<meta name="theme-slot" content="head">']);
        $html = publicRequest($app, '/en/');
        contains('max-w-[16ch]', $html); contains('aria-label="Site"', $html); contains('<meta name="theme-slot" content="head"></head>', $html);
        $log = file_get_contents($root . '/themes.log');
        contains('starter template public/home', $log); contains('undefined_theme_function', $log);
    });
});

test('Theme API: partials are isolated, slots come only from the theme, admin templates are never themed', function () {
    themeSandbox(function (string $root, callable $write) {
        $app = publicApp(); $db = $app['db'];
        $builtIn = publicRequest($app, '/en/');
        $write('iso', ['theme.json' => themeJson('iso'), 'partials/nav.php' => '<?php $leak = "x"; ?><nav>NAV</nav>',
            'partials/mark.php' => '<?= isset($leak) || isset($stacked) ? "LEAK" : "CLEAN" ?>', 'slots/footer.php' => 'FOOTER-SLOT',
            'layouts/admin.php' => 'HACKED', 'admin/posts.php' => 'HACKED']);
        (new Themes($db))->activate('iso');
        $html = publicRequest($app, '/en/');
        contains('CLEAN', $html); ok(!str_contains($html, 'LEAK'), 'Variables leaked between partials'); contains('FOOTER-SLOT', $html);
        ok(!str_contains($builtIn, 'FOOTER-SLOT'), 'Built-in renders no slot output');
        $view = new Core\View(dirname(__DIR__) . '/views', $root . '/themes/iso');
        ok(!str_contains($view->partial('system/mark', ['siteIcon' => '']), 'CLEAN'), 'System partials are not themed');
        throws(fn () => $view->partial('../secret'), InvalidArgumentException::class);
    });
});

test('Theme API: preview is only for the signed-in admin and shows Meteor’s banner', function () {
    themeSandbox(function () {
        $_SESSION = ['theme_preview' => 'starter'];
        $admin = publicApp(true); (new Themes($admin['db']))->installStarter();
        $html = publicRequest($admin, '/en/');
        contains('starter-hero', $html); contains('Previewing Starter', $html); contains('href="/admin/appearance#themes"', $html);
        $visitor = publicApp(false);
        $html = publicRequest($visitor, '/en/');
        ok(!str_contains($html, 'starter-hero'), 'Visitors never see a preview'); ok(!str_contains($html, 'Previewing'));
        $_SESSION['theme_preview'] = 'missing';
        ok(!str_contains(publicRequest($admin, '/en/'), 'Previewing'), 'An unknown preview is ignored');
    });
});

test('Theme API: asset route serves only allowed files of the active theme', function () {
    themeSandbox(function (string $root, callable $write) {
        $visitor = publicApp(false); $db = $visitor['db'];
        $write('assets', ['theme.json' => themeJson('assets'), 'theme.css' => 'body{}', 'img/a.svg' => '<svg/>', 'home.php' => '<?php echo 1;',
            'data.json' => '{}', '.hidden.css' => 'x', 'big.png' => str_repeat('x', Themes::ASSET_MAX + 1), 'script.js' => 'x']);
        symlink(dirname(__DIR__) . '/README.md', $root . '/themes/assets/link.css');
        $write('other', ['theme.json' => themeJson('other'), 'theme.css' => 'other{}']);
        (new Themes($db))->activate('assets');
        $get = function (array $app, string $slug, string $path): array { http_response_code(200); $body = $app['feeds']->themeAsset($slug, $path); return [http_response_code(), $body]; };
        same([200, 'body{}'], $get($visitor, 'assets', 'theme.css'));
        same([200, '<svg/>'], $get($visitor, 'assets', 'img/a.svg'));
        foreach (['home.php', 'data.json', '.hidden.css', 'big.png', 'script.js', 'link.css', '../other/theme.css', 'img/../theme.css', 'missing.css', 'theme.json'] as $path) {
            same(404, $get($visitor, 'assets', $path)[0], $path);
        }
        same(404, $get($visitor, 'other', 'theme.css')[0], 'Inactive theme for visitors');
        same([200, 'other{}'], $get(publicApp(true), 'other', 'theme.css'), 'Admins can load any usable theme for previews');
        contains('/themes/assets/theme.css?v=' . hash('sha256', 'body{}'), (new Themes($db))->object((new Themes($db))->find('assets'))->asset('theme.css'));
    });
});

test('Theme API: deprecated property names log once', function () {
    themeSandbox(function (string $root) {
        $object = new class { public string $title = 'T'; public function __get(string $name): mixed { return Deprecated::read($this, $name, ['heading' => 'title']); } };
        same('T', $object->heading); same('T', $object->heading);
        same(1, substr_count(file_get_contents($root . '/themes.log'), 'heading'));
        throws(fn () => $object->missing, LogicException::class);
    });
});

test('Admin HTTP: Appearance lists themes, installs the starter, activates, previews and saves settings', function () {
    $c = new AdminHttpClient();
    try {
        file_put_contents($c->root . '/VERSION', "0.5.0\n");
        $token = $c->token();
        $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password']);
        [, $html] = $c->request('/admin/appearance');
        contains('id="themes"', $html); contains('Built-in', $html); contains('Install the starter theme', $html);
        $token = $c->token('/admin/appearance');
        [$h] = $c->request('/admin/appearance/themes', 'POST', ['action' => 'install_starter']); contains('403 Forbidden', $h);
        [$h] = $c->request('/admin/appearance/themes', 'POST', ['csrf_token' => $token, 'action' => 'install_starter']); contains('Location: /admin/appearance#themes', $h);
        ok(is_file($c->root . '/storage/themes/starter/theme.json'), 'Starter copied into storage/themes');
        [$h] = $c->request('/admin/appearance/themes', 'POST', ['csrf_token' => $token, 'action' => 'preview', 'slug' => 'starter']); contains('Location: /en/', $h);
        [$h, $html] = $c->request('/en/'); contains('Previewing Starter', $html); contains('Cache-Control: private, no-store', $h);
        [, $html] = $c->request('/admin/appearance'); contains('Previewing', $html); contains('Stop preview', $html);
        $c->request('/admin/appearance/themes', 'POST', ['csrf_token' => $token, 'action' => 'activate', 'slug' => 'starter']);
        [, $html] = $c->request('/admin/appearance'); contains('Starter settings', $html); contains('Show categories next to the latest posts', $html); contains('type="color"', $html);
        [$h] = $c->request('/admin/appearance/themes', 'POST', ['csrf_token' => $token, 'action' => 'settings', 'slug' => 'starter', 'theme' => ['hero' => 'giant', 'accent' => '#123456']]);
        contains('Location: /admin/appearance#theme-settings', $h);
        [, $html] = $c->request('/admin/appearance'); contains('Choose one of the options.', $html); contains('aria-invalid="true"', $html);
        $c->request('/admin/appearance/themes', 'POST', ['csrf_token' => $token, 'action' => 'settings', 'slug' => 'starter', 'theme' => ['hero' => 'compact', 'tagline' => 'Hello', 'accent' => '#123456']]);
        $c->request('/admin/appearance/themes', 'POST', ['csrf_token' => $token, 'action' => 'stop_preview']);
        $c->cookie = '';
        [$h, $html] = $c->request('/en/');
        contains('starter-hero--compact', $html); contains('Hello', $html); ok(!str_contains($html, 'Previewing'));
        contains("script-src 'none'", $h);
        [$h, $css] = $c->request('/theme-settings.css'); same(':root{--theme-accent:#123456}', $css); contains('text/css', $h);
        [$h] = $c->request('/themes/starter/theme.css'); contains('text/css', $h); contains('X-Content-Type-Options: nosniff', $h);
        [$h] = $c->request('/themes/starter/theme.json'); contains('404', $h);
        [$h] = $c->request('/themes/starter/public/home.php'); contains('404', $h);
    } finally { $c->close(); }
});
