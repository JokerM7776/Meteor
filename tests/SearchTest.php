<?php
use App\Repositories\Search;

test('Search: every word must match published text in that language; title matches rank first', function () {
    $db = database(); locale($db); locale($db, 'ms', 'live', 0, 1);
    $editor = new App\Services\ContentEditor($db);
    $a = $editor->save(false, 0, 'en', ['title' => 'Comet notes', 'summary' => 'Tails', 'content_md' => 'A bright **meteor** shower over 100% of the sky_line.', 'status' => 'published']);
    $b = $editor->save(false, 0, 'en', ['title' => 'Meteor guide', 'summary' => '', 'content_md' => 'How to watch.', 'status' => 'published']);
    $editor->save(false, 0, 'en', ['title' => 'Meteor draft', 'content_md' => 'meteor', 'status' => 'draft']);
    $editor->save(true, 0, 'en', ['title' => 'About meteors', 'content_md' => 'Ünïcode Ärger', 'status' => 'published', 'is_published' => '1']);
    $editor->save(false, 0, 'ms', ['title' => 'Meteor Melayu', 'content_md' => 'x', 'status' => 'published']);
    $db->query('UPDATE posts SET is_published = 1');
    $search = new Search($db);
    same(['Meteor guide', 'About meteors', 'Comet notes'], array_column($search->find('en', 'METEOR'), 'title'));
    same(['Comet notes'], array_column($search->find('en', 'meteor shower'), 'title'));
    same(['Comet notes'], array_column($search->find('en', '100%'), 'title'));
    same(['Comet notes'], array_column($search->find('en', '%'), 'title')); same(['Comet notes'], array_column($search->find('en', 'y_l'), 'title')); same([], $search->find('en', 'y%l')); same([], $search->find('en', '   '));
    same(['About meteors'], array_column($search->find('en', 'ärger'), 'title'));
    same('page', $search->find('en', 'ärger')[0]['type']);
    same('A bright <mark>meteor</mark> <mark>shower</mark> over 100% of the skyline.', $search->find('en', 'meteor shower')[0]['snippet']);
    same(6, count(Search::terms('a b c d e f g h')));
    same('&lt;b&gt; <mark>amp</mark> &amp;', Search::snippet('<b> amp &', ['amp']));
    $db->query('UPDATE posts SET published_at = ? WHERE id = ?', ['2999-01-01 00:00:00', $b]);
    same(['About meteors', 'Comet notes'], array_column($search->find('en', 'meteor'), 'title'));
});
