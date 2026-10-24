<?php
use App\Repositories\Announcements;
use App\Services\AnnouncementEditor;

test('Announcements: validation, language scope, order and the live list', function () {
    $db = database(); locale($db); locale($db, 'zh-hans', 'live', 0, 1); locale($db, 'ms', 'off', 0, 2);
    $editor = new AnnouncementEditor($db); $repository = new Announcements($db);
    foreach ([['message' => ' '], ['message' => 'x', 'title' => "two\nlines"], ['message' => str_repeat('a', 1001)], ['message' => "bell\x07"],
        ['message' => 'x', 'button_text' => 'Go'], ['message' => 'x', 'button_url' => '/en/'], ['message' => 'x', 'button_text' => 'Go', 'button_url' => 'javascript:alert(1)'],
        ['message' => 'x', 'button_text' => 'Go', 'button_url' => '//evil.test'], ['message' => 'x', 'scope' => 'some'], ['message' => 'x', 'scope' => 'some', 'locales' => ['xx']]] as $bad) {
        throws(fn () => $editor->save(0, $bad), InvalidArgumentException::class);
    }
    throws(fn () => $editor->save(99, ['message' => 'x']), InvalidArgumentException::class);
    same(0, (int) $db->one('SELECT COUNT(*) AS n FROM announcements')['n']);

    $all = $editor->save(0, ['title' => ' Sale ', 'message' => "Line one\r\nLine two", 'button_text' => 'Shop', 'button_url' => '/{locale}/posts']);
    $chinese = $editor->save(0, ['message' => '只有中文', 'scope' => 'some', 'locales' => ['zh-hans', 'en'], 'button_text' => 'Read', 'button_url' => 'https://example.com/']);
    $hidden = $editor->save(0, ['message' => 'Hidden', 'visible' => '0']);
    same('en zh-hans', $repository->find($chinese)['locale_codes'], 'Codes follow the language order');
    same("Line one\nLine two", $repository->find($all)['message']); same('Sale', $repository->find($all)['title']);
    same([$all, $chinese], array_column($repository->live('en'), 'id'));
    $zh = $repository->live('zh-hans');
    same('/zh-hans/posts', $zh[0]['button_url']); same(false, $zh[0]['external']); same(true, $zh[1]['external']);
    same([$all], array_column($repository->live('ms'), 'id'));

    $editor->save($chinese, ['message' => 'Only Chinese', 'scope' => 'some', 'locales' => ['zh-hans']]);
    same([$all], array_column($repository->live('en'), 'id')); same('', $repository->find($chinese)['button_url']);
    $editor->saveList(['items' => [$all => ['sort_order' => '3', 'visible' => '1'], $chinese => ['sort_order' => '1'], $hidden => ['sort_order' => '2', 'visible' => '1']]]);
    same([$chinese, $hidden, $all], array_column($repository->all(), 'id')); same([$chinese, $hidden, $all], array_column($repository->live('zh-hans'), 'id'));
    throws(fn () => $editor->saveList(['items' => [999 => ['visible' => '1']]]), InvalidArgumentException::class);
    throws(fn () => $editor->saveList(['items' => [$all => ['sort_order' => '-1']]]), InvalidArgumentException::class);
    $editor->delete($hidden); same(null, $repository->find($hidden));

    // A language deleted later simply stops matching.
    $db->query("UPDATE announcements SET locale_codes = 'gone' WHERE id = ?", [$chinese]);
    same([$all], array_column($repository->live('zh-hans'), 'id'));
});

test('Announcements HTTP: admin create, list, edit errors, public bar without scripts, delete', function () {
    $c = new AdminHttpClient();
    try {
        $token = $c->token(); $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password']);
        $token = $c->token('/admin/announcements');
        [$h, $html] = $c->request('/admin/announcements'); contains('200 OK', $h); contains('href="/admin/announcements" aria-current="page"', $html); contains('No announcements yet', $html);
        [$h] = $c->request('/admin/announcements/new', 'POST', ['message' => 'No token']); contains('403 Forbidden', $h);

        [$h] = $c->request('/admin/announcements/new', 'POST', ['csrf_token' => $token, 'title' => 'Keep <me>', 'message' => 'Body', 'button_text' => 'Go', 'button_url' => 'javascript:alert(1)']);
        contains('Location: /admin/announcements/new', $h);
        [, $html] = $c->request('/admin/announcements/new'); contains('Button link: use a URL', $html); contains('value="Keep &lt;me&gt;"', $html); contains('value="javascript:alert(1)"', $html);

        [$h] = $c->request('/admin/announcements/new', 'POST', ['csrf_token' => $token, 'title' => 'First <b>', 'message' => "Hello\nworld", 'button_text' => 'Read', 'button_url' => '/{locale}/posts', 'scope' => 'all', 'visible' => '1']);
        contains('Location: /admin/announcements', $h);
        $c->request('/admin/announcements/new', 'POST', ['csrf_token' => $token, 'message' => 'Second', 'scope' => 'some', 'locales' => ['en'], 'visible' => '1']);
        $c->request('/admin/announcements/new', 'POST', ['csrf_token' => $token, 'message' => 'Japanese only', 'scope' => 'some', 'locales' => ['ja'], 'visible' => '1']);
        [$first, $second, $third] = array_map('intval', array_column($c->db->all('SELECT id FROM announcements ORDER BY sort_order'), 'id'));
        [, $html] = $c->request('/admin/announcements'); contains('First &lt;b&gt;', $html); contains('data-sort-order', $html); contains('All languages', $html); contains('<span lang="ja">ja</span>', $html);
        [, $html] = $c->request('/admin/announcements/' . $third); contains('value="ja" checked', $html); contains('value="some" checked', $html);
        [$h] = $c->request('/admin/announcements/999'); contains('404', $h);
        [$h] = $c->request('/admin/announcements/new/delete'); contains('404', $h);

        $c->cookie = '';
        [$h, $html] = $c->request('/en/'); contains('200 OK', $h); ok(!str_contains($html, '<script'));
        contains('<section aria-label="Announcements"', $html); contains('<strong class="font-semibold text-white">First &lt;b&gt;</strong> Hello<br>', $html);
        contains('href="/en/posts"', $html); contains('aria-label="Announcement 1 of 2" checked', $html); contains('aria-label="Announcement 2 of 2">', $html);
        contains('<label for="announcement-2" title="Previous"', $html); contains('<label for="announcement-2" title="Next"', $html); ok(!str_contains($html, 'Japanese only'));
        [, $html] = $c->request('/en/missing-page'); ok(!str_contains($html, 'Announcements'), 'No bar on 404 pages');

        $token = $c->token(); $c->request('/admin/login', 'POST', ['csrf_token' => $token, 'username' => 'editor', 'password' => 'a long password']);
        $token = $c->token('/admin/announcements');
        $c->request('/admin/announcements', 'POST', ['csrf_token' => $token, 'items' => [$first => ['visible' => '0', 'sort_order' => '2'], $second => ['visible' => '1', 'sort_order' => '1']]]);
        [, $html] = $c->request('/en/'); contains('<p class="w-full py-1.5 sm:w-auto sm:min-w-0 sm:flex-1">Second</p>', $html); ok(!str_contains($html, 'type="radio"'), 'One announcement needs no arrows'); ok(!str_contains($html, 'Hello'));
        [, $html] = $c->request('/admin/announcements/' . $second . '/delete'); contains('Delete announcement?', $html);
        [$h] = $c->request('/admin/announcements/' . $second . '/delete', 'POST', ['csrf_token' => $token]); contains('Location: /admin/announcements', $h);
        same(null, $c->db->one('SELECT id FROM announcements WHERE id = ?', [$second]));
    } finally { $c->close(); }
});
