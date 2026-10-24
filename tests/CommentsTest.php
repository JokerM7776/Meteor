<?php
use App\Repositories\Comments;
use App\Services\{CommentForm, CommentSettings, Webmentions};

function commentFixture(array $settings = []): array {
    $db = database(); locale($db); locale($db, 'ms', 'live', 0, 1);
    $id = (new App\Services\ContentEditor($db))->save(false, 0, 'en', ['title' => 'Hello', 'content_md' => 'Text', 'status' => 'published', 'is_published' => '1', 'comments_enabled' => '1']);
    foreach ($settings + ['comments_enabled' => '1'] as $key => $value) { $db->upsert('settings', ['key' => $key, 'locale' => null, 'value' => $value], ['key', 'locale_scope']); }
    $db->query('UPDATE posts SET published_at = ?', ['2020-01-01 00:00:00']);
    return [$db, $db->one('SELECT * FROM posts WHERE id = ?', [$id])];
}
function commentInput(CommentForm $form, array $post, array $values = []): array {
    return $values + ['name' => 'Ada', 'email' => 'Ada@Example.test', 'url' => '', 'body' => "Nice post.\n\nSecond paragraph.", 'token' => $form->token((int) $post['id'], 'en', time() - 10)];
}

test('Comments: honeypot, timer, validation, rate limit and moderation modes', function () {
    [$db, $post] = commentFixture();
    $form = new CommentForm($db);
    same('dropped', $form->submit($post, 'en', commentInput($form, $post, ['homepage' => 'http://spam.test']), '1.1.1.1')['result']);
    same('dropped', $form->submit($post, 'en', commentInput($form, $post, ['token' => $form->token((int) $post['id'], 'en')]), '1.1.1.1')['result']);
    $expired = $form->submit($post, 'en', commentInput($form, $post, ['token' => $form->token((int) $post['id'], 'en', time() - CommentForm::MAX_AGE - 5)]), '1.1.1.1');
    same('comment_error_expired', $expired['error']); ok($expired['values']['token'] !== '', 'fresh token');
    same('comment_error_expired', $form->submit($post, 'ms', commentInput($form, $post), '1.1.1.1')['error']);
    foreach ([['name' => ''], ['name' => str_repeat('a', 101)], ['email' => 'nope'], ['url' => 'javascript:alert(1)'], ['url' => 'ftp://x.test'], ['body' => ''], ['body' => str_repeat('a', 5001)]] as $bad) {
        same('error', $form->submit($post, 'en', commentInput($form, $post, $bad), '1.1.1.1')['result'], json_encode($bad));
    }
    $saved = $form->submit($post, 'en', commentInput($form, $post), '1.1.1.1');
    same('pending', $saved['status']);
    $row = $db->one('SELECT * FROM comments WHERE id = ?', [$saved['id']]);
    same('ada@example.test', $row['author_email']); same(64, strlen($row['ip_hash'])); ok($row['ip_hash'] !== hash('sha256', '1.1.1.1'));
    for ($i = 0; $i < 4; $i++) { $form->submit($post, 'en', commentInput($form, $post), '2.2.2.2'); }
    same('saved', $form->submit($post, 'en', commentInput($form, $post), '2.2.2.2')['result']);
    same('comment_error_rate', $form->submit($post, 'en', commentInput($form, $post), '2.2.2.2')['error']);
    // Returning commenters: an approved comment from the same email publishes the next one.
    $db->upsert('settings', ['key' => 'comments_moderation', 'locale' => null, 'value' => 'returning'], ['key', 'locale_scope']);
    same('pending', $form->submit($post, 'en', commentInput($form, $post), '3.3.3.3')['status']);
    $db->query("UPDATE comments SET status = 'approved' WHERE id = ?", [$saved['id']]);
    same('approved', $form->submit($post, 'en', commentInput($form, $post), '3.3.3.3')['status']);
    same('pending', $form->submit($post, 'en', commentInput($form, $post, ['email' => '']), '3.3.3.3')['status']);
    $db->upsert('settings', ['key' => 'comments_moderation', 'locale' => null, 'value' => 'open'], ['key', 'locale_scope']);
    same('approved', $form->submit($post, 'en', commentInput($form, $post, ['email' => '']), '4.4.4.4')['status']);
    same('pending', $form->submit($post, 'en', commentInput($form, $post, ['body' => 'https://a.test https://b.test https://c.test']), '4.4.4.4')['status']);
});
test('Comments: replies stay one level deep and the discussion is per language', function () {
    [$db, $post] = commentFixture(['comments_moderation' => 'open']);
    $form = new CommentForm($db);
    $top = $form->submit($post, 'en', commentInput($form, $post), '1.1.1.1')['id'];
    $reply = $form->submit($post, 'en', commentInput($form, $post, ['parent_id' => $top, 'name' => 'Bob']), '1.1.1.2')['id'];
    $nested = $form->submit($post, 'en', commentInput($form, $post, ['parent_id' => $reply, 'name' => 'Cy']), '1.1.1.3')['id'];
    same($top, (int) $db->one('SELECT parent_id FROM comments WHERE id = ?', [$nested])['parent_id']);
    $form->submit($post, 'en', commentInput($form, $post, ['parent_id' => 999999]), '1.1.1.4');
    $db->query("INSERT INTO comments (post_id, locale, kind, mention_type, author_name, body, source_url, status) VALUES (?, 'en', 'webmention', 'like', 'Zed', '', 'https://zed.test/1', 'approved')", [$post['id']]);
    $d = (new Comments($db))->discussion((int) $post['id'], 'en');
    same(2, count($d['threads'])); same(['Bob', 'Cy'], array_column($d['threads'][0]['replies'], 'author_name')); same(4, $d['count']); same('Zed', $d['reactions'][0]['author_name']);
    same(0, (new Comments($db))->discussion((int) $post['id'], 'ms')['count']);
    same("<p>a &lt;b&gt; <a href=\"https://x.test/?a=1&amp;b=2\" rel=\"nofollow ugc noopener\">https://x.test/?a=1&amp;b=2</a>.<br>\nc</p><p>d</p>", Comments::html("a <b> https://x.test/?a=1&b=2.\nc\n\n\nd"));
    contains('href="https://x.test/&quot;onmouseover=x"', Comments::html('https://x.test/"onmouseover=x'));
});
test('Comments: CAPTCHA step uses the chosen provider and keeps the original token', function () {
    [$db, $post] = commentFixture(['comments_captcha_provider' => 'recaptcha', 'recaptcha_site_key' => str_repeat('s', 40), 'recaptcha_secret_key' => str_repeat('k', 40)]);
    $calls = [];
    $form = new CommentForm($db, function (string $url, array $fields) use (&$calls): array { $calls[] = [$url, $fields]; return ['success' => $fields['response'] === 'good']; });
    $input = commentInput($form, $post);
    $check = $form->submit($post, 'en', $input, '1.1.1.1');
    same('check', $check['result']); same($input['token'], $check['values']['token']); same([], $calls);
    same('comment_error_check', $form->submit($post, 'en', $input + ['g-recaptcha-response' => 'bad'], '1.1.1.1')['error']);
    same('saved', $form->submit($post, 'en', $input + ['g-recaptcha-response' => 'good'], '1.1.1.1')['result']);
    same('https://www.google.com/recaptcha/api/siteverify', $calls[0][0]); same(str_repeat('k', 40), $calls[0][1]['secret']);
    // Turnstile reuses the Security keys; with no keys there is no CAPTCHA step.
    $settings = new CommentSettings($db);
    $settings->save(['comments_enabled' => '1', 'comments_captcha' => '1', 'comments_captcha_provider' => 'turnstile', 'comments_moderation' => 'hold']);
    same(null, $settings->captcha());
    $db->upsert('settings', ['key' => 'turnstile_site_key', 'locale' => null, 'value' => 'site-key-123456'], ['key', 'locale_scope']);
    $db->upsert('settings', ['key' => 'turnstile_secret_key', 'locale' => null, 'value' => 'secret-key-123456'], ['key', 'locale_scope']);
    same('cf-turnstile-response', $settings->captcha()['field']);
    same(str_repeat('k', 40), $settings->values()['recaptcha_secret_key']);
    throws(fn () => $settings->save(['comments_captcha' => '1', 'comments_captcha_provider' => 'recaptcha', 'recaptcha_forget' => '1', 'comments_moderation' => 'hold']), InvalidArgumentException::class);
    throws(fn () => $settings->save(['comments_moderation' => 'maybe']), InvalidArgumentException::class);
    throws(fn () => $settings->save(['comments_moderation' => 'hold', 'recaptcha_site_key' => 'bad key!']), InvalidArgumentException::class);
});
test('Webmention: SafeHttp refuses private addresses, odd ports and unsafe redirects', function () {
    $resolve = fn (string $host) => ['internal.test' => ['10.0.0.5'], 'six.test' => ['::1'], 'public.test' => ['93.184.216.34']][$host] ?? [];
    $requests = [];
    $http = new Core\SafeHttp(function (string $method, string $url, array $headers, ?string $body, string $ip) use (&$requests): array {
        $requests[] = "$method $url $ip";
        return str_ends_with($url, '/hop') ? ['status' => 302, 'headers' => ['location' => ['http://internal.test/admin']], 'body' => ''] : ['status' => 200, 'headers' => [], 'body' => 'ok'];
    }, $resolve);
    foreach (['http://127.0.0.1/', 'http://[::1]/', 'http://169.254.169.254/latest', 'http://internal.test/', 'http://six.test/', 'http://public.test:8080/', 'file:///etc/passwd', 'http://user:pw@public.test/', 'http://nowhere.test/'] as $bad) {
        throws(fn () => $http->get($bad), RuntimeException::class);
    }
    same('ok', $http->get('https://public.test/a')['body']); same('GET https://public.test/a 93.184.216.34', $requests[0]);
    throws(fn () => $http->get('https://public.test/hop'), RuntimeException::class);
    same('https://a.test/x/c?q#f', Core\SafeHttp::resolve('https://a.test/x/y', 'c?q#f'));
    same('https://a.test/c', Core\SafeHttp::resolve('https://a.test/x/y', '../../c'));
    same('https://a.test/root', Core\SafeHttp::resolve('https://a.test/x/y', '/root'));
    same('http://b.test/z', Core\SafeHttp::resolve('http://a.test/', '//b.test/z'));
    same('https://a.test/x/y?q', Core\SafeHttp::resolve('https://a.test/x/y?old', '?q'));
});
test('Webmention: receiving validates the target, verifies the source and follows updates and deletions', function () {
    [$db, $post] = commentFixture();
    $pages = [];
    $http = new Core\SafeHttp(function (string $method, string $url) use (&$pages): array {
        return $pages[$url] ?? ['status' => 404, 'headers' => [], 'body' => ''];
    }, fn () => ['93.184.216.34']);
    $wm = new Webmentions($db, 'https://meteor.example', $http);
    $target = 'https://meteor.example/en/posts/hello';
    same(400, $wm->receive('https://other.test/a', 'https://evil.test/en/posts/hello', '1.1.1.1')[0]);
    same(400, $wm->receive('https://other.test/a', 'https://meteor.example/en/posts/missing', '1.1.1.1')[0]);
    same(400, $wm->receive('javascript:x', $target, '1.1.1.1')[0]);
    same(400, $wm->receive($target, $target . '/', '1.1.1.1')[0]);
    same(400, $wm->receive(['x'], $target, '1.1.1.1')[0]);
    same(202, $wm->receive('https://other.test/reply', $target, '1.1.1.1')[0]);
    same(202, $wm->receive('https://other.test/reply', $target, '1.1.1.1')[0]);
    same(1, count($db->all('SELECT * FROM webmentions')));
    $pages['https://other.test/reply'] = ['status' => 200, 'headers' => [], 'body' => '<html><title>T</title><div class="h-entry"><a class="p-author h-card" href="/me">Ann <b>Lee</b></a>'
        . '<a class="u-in-reply-to" href="https://meteor.example/en/posts/hello">re</a><div class="e-content">Great <script>x</script> point!</div></div>'];
    same(1, $wm->process());
    $row = $db->one("SELECT * FROM comments WHERE kind = 'webmention'");
    same('reply', $row['mention_type']); same('Ann Lee', $row['author_name']); same('https://other.test/me', $row['author_url']); same('pending', $row['status']);
    same('Great x point!', $row['body']);
    // Updated source: re-sent mention updates the row; a removed link deletes it.
    $pages['https://other.test/reply']['body'] = str_replace('u-in-reply-to', 'u-like-of', $pages['https://other.test/reply']['body']);
    $wm->receive('https://other.test/reply', $target, '1.1.1.1'); $wm->process();
    same('like', $db->one("SELECT mention_type FROM comments WHERE kind = 'webmention'")['mention_type']);
    $pages['https://other.test/reply'] = ['status' => 410, 'headers' => [], 'body' => ''];
    $wm->receive('https://other.test/reply', $target, '1.1.1.1'); $wm->process();
    same(null, $db->one("SELECT id FROM comments WHERE kind = 'webmention'"));
    // Comments off: the endpoint refuses.
    $db->upsert('settings', ['key' => 'comments_enabled', 'locale' => null, 'value' => '0'], ['key', 'locale_scope']);
    same(404, $wm->receive('https://other.test/b', $target, '1.1.1.1')[0]);
});
test('Webmention: sending discovers endpoints from headers and HTML and retries server errors', function () {
    [$db, $post] = commentFixture(['webmention_send' => '1']);
    $db->query('UPDATE post_translations SET content_html = ? WHERE post_id = ?', ['<p><a href="https://a.test/post">a</a> <a href="https://b.test/p?x=1&amp;y=2#frag">b</a> <a href="https://meteor.example/en/">self</a> <a href="https://c.test/">c</a></p>', $post['id']]);
    $sent = [];
    $http = new Core\SafeHttp(function (string $method, string $url, array $headers, ?string $body) use (&$sent): array {
        if ($method === 'POST') { $sent[] = [$url, $body]; return ['status' => str_contains($url, 'c.test') ? 503 : 202, 'headers' => [], 'body' => '']; }
        return match ($url) {
            'https://a.test/post' => ['status' => 200, 'headers' => ['link' => ['<https://a.test/style.css>; rel=stylesheet, </wm?x=1>; rel="other webmention"']], 'body' => ''],
            'https://b.test/p?x=1&y=2' => ['status' => 200, 'headers' => ['content-type' => ['text/html']], 'body' => '<a href="/no">x</a><link rel="webmention" href="endpoint">'],
            default => ['status' => 200, 'headers' => [], 'body' => '<link rel="webmention" href="https://c.test/wm">'],
        };
    }, fn () => ['93.184.216.34']);
    $wm = new Webmentions($db, 'https://meteor.example', $http);
    same(3, $wm->queueOutgoing((int) $post['id'], 'en'));
    same(3, $wm->process());
    same(['https://a.test/wm?x=1', 'https://b.test/endpoint', 'https://c.test/wm'], array_column($sent, 0));
    same('source=https%3A%2F%2Fmeteor.example%2Fen%2Fposts%2Fhello&target=https%3A%2F%2Fa.test%2Fpost', $sent[0][1]);
    $c = $db->one("SELECT * FROM webmentions WHERE target = 'https://c.test/'");
    same('queued', $c['status']); same(1, (int) $c['attempts']); ok($c['next_try_at'] > gmdate('Y-m-d H:i:s'));
    // Links removed later are notified once more, so the receiver can drop the mention.
    $db->query('UPDATE post_translations SET content_html = ? WHERE post_id = ?', ['<p>no links</p>', $post['id']]);
    same(3, $wm->queueOutgoing((int) $post['id'], 'en'));
    $db->upsert('settings', ['key' => 'webmention_send', 'locale' => null, 'value' => '0'], ['key', 'locale_scope']);
    same(0, $wm->queueOutgoing((int) $post['id'], 'en'));
});
test('Comments HTTP: zero-JS form, CAPTCHA page, webmention endpoint and admin moderation', function () {
    $c = new AdminHttpClient();
    try {
        $db = $c->db;
        $id = (new App\Services\ContentEditor($db))->save(false, 0, 'en', ['title' => 'Hello', 'content_md' => 'Text', 'status' => 'published', 'is_published' => '1', 'comments_enabled' => '1']);
        $db->query('UPDATE posts SET published_at = ?', ['2020-01-01 00:00:00']);
        [$h, $html] = $c->request('/en/posts/hello');
        ok(!str_contains($html, 'id="comments"')); ok(!str_contains($h, 'rel="webmention"'));
        $db->upsert('settings', ['key' => 'comments_enabled', 'locale' => null, 'value' => '1'], ['key', 'locale_scope']);
        [$h, $html] = $c->request('/en/posts/hello');
        contains('id="comment-form"', $html); contains('action="/en/posts/hello/comments"', $html); ok(!str_contains($html, '<script'));
        contains('Link: <https://meteor.example/webmention>; rel="webmention"', $h); contains('<link rel="webmention" href="https://meteor.example/webmention">', $html);
        $token = (new CommentForm($db))->token($id, 'en', time() - 10);
        $data = ['name' => 'Ada', 'email' => '', 'url' => '', 'body' => 'Hi <b>there</b>', 'token' => $token];
        [$h] = $c->request('/en/posts/hello/comments', 'POST', $data);
        contains('303 See Other', $h); contains('Location: /en/posts/hello?comment=pending#comments', $h);
        [$h, $html] = $c->request('/en/posts/hello/comments', 'POST', ['name' => ''] + $data);
        contains('422', $h); contains('role="alert"', $html); contains('aria-invalid="true"', $html); contains('Hi &lt;b&gt;there&lt;/b&gt;', $html);
        $db->upsert('settings', ['key' => 'turnstile_site_key', 'locale' => null, 'value' => 'site-key-123456'], ['key', 'locale_scope']);
        $db->upsert('settings', ['key' => 'turnstile_secret_key', 'locale' => null, 'value' => 'secret-key-123456'], ['key', 'locale_scope']);
        [$h, $html] = $c->request('/en/posts/hello/comments', 'POST', $data);
        contains('200 OK', $h); contains("script-src https://challenges.cloudflare.com; frame-src https://challenges.cloudflare.com;", $h); contains('<script src="https://challenges.cloudflare.com/turnstile/v0/api.js"', $html);
        contains('class="cf-turnstile" data-sitekey="site-key-123456"', $html); contains('name="token" value="' . $token . '"', $html); contains('Cache-Control: no-store', $h);
        [$h, $body] = $c->request('/webmention', 'POST', ['source' => 'https://other.test/a', 'target' => 'https://meteor.example/en/posts/hello']);
        contains('202 Accepted', $h); contains('text/plain', $h);
        [$h] = $c->request('/webmention'); contains('405', $h);
        // Search: on by default, noindex, zero JS; off turns /search back into an ordinary (missing) page.
        [$h, $html] = $c->request('/en/search?q=hello');
        contains('200 OK', $h); contains('href="/en/search"', $html); contains('noindex', $html); contains('<a class="accent" href="/en/posts/hello">Hello</a>', $html); ok(!str_contains($html, '<script'));
        $db->upsert('settings', ['key' => 'header_show_search', 'locale' => null, 'value' => '0'], ['key', 'locale_scope']);
        [$h, $html] = $c->request('/en/search?q=hello'); contains('404', $h); ok(!str_contains($html, 'href="/en/search"'));
        // Admin: pending list, approve, reply, settings.
        $loginToken = $c->token(); $c->request('/admin/login', 'POST', ['csrf_token' => $loginToken, 'username' => 'editor', 'password' => 'a long password']);
        $csrf = $c->token('/admin/comments');
        [, $html] = $c->request('/admin/comments');
        contains('Ada', $html); contains('Hi &lt;b&gt;there&lt;/b&gt;', $html); contains('waiting for approval', $html);
        $comment = (int) $db->one('SELECT id FROM comments')['id'];
        [$h] = $c->request('/admin/comments?status=pending', 'POST', ['csrf_token' => $csrf, 'row' => 'approve:' . $comment]);
        contains('Location: /admin/comments?status=pending&page=1', $h);
        same('approved', $db->one('SELECT status FROM comments WHERE id = ?', [$comment])['status']);
        [$h] = $c->request('/admin/comments/' . $comment . '/reply', 'POST', ['csrf_token' => $csrf, 'body' => 'Thanks!']);
        contains('303 See Other', $h);
        $reply = $db->one('SELECT * FROM comments WHERE is_owner = 1');
        same($comment, (int) $reply['parent_id']); same('approved', $reply['status']);
        [, $html] = $c->request('/en/posts/hello?comment=published');
        contains('Thanks!', $html); contains('role="status"', $html); contains('>Author<', $html); contains('href="/en/posts/hello?reply=' . $comment . '#comment-form"', $html);
        [, $html] = $c->request('/admin/comments?tab=settings');
        contains('name="comments_moderation"', $html); contains('bin/cron.php', $html); contains('uses the keys from Security', $html);
        [$h] = $c->request('/admin/comments', 'POST', ['csrf_token' => $csrf, 'action' => 'delete', 'ids' => [$comment]]);
        same(0, (int) $db->one('SELECT COUNT(*) AS n FROM comments')['n']);
        [$h] = $c->request('/admin/comments', 'POST', ['action' => 'delete', 'ids' => [1]]); contains('403', $h);
    } finally { $c->close(); }
});
