<?php
test('Dates: site time zone, date formats, 12-hour clock and the editor field', function () {
    try {
        Core\Dates::configure('MMM d, yyyy', '12', 'Asia/Kuala_Lumpur');
        same('Oct 25, 2026', Core\Dates::date('2026-10-24 20:30:00', 'en'));
        same('4:30 AM', Core\Dates::time('2026-10-24 20:30:00', 'en'));
        same('Oct 25, 2026, 4:30 AM', Core\Dates::dateTime('2026-10-24 20:30:00', 'en'));
        same('2026-10-25T04:30', Core\Dates::toInput('2026-10-24 20:30:00'));
        same('2026-10-24 20:30:00', App\Services\ContentEditor::date('2026-10-25T04:30'));
        same('October', Core\Dates::date('2026-10-01', 'en', 'LLLL'), 'a bare date is a calendar day and does not move');
        same('UTC+08:00', Core\Dates::offset('Asia/Kuala_Lumpur'));
        Core\Dates::configure('locale', '24', 'UTC');
        same('2026年10月24日', Core\Dates::date('2026-10-24 20:30:00', 'zh-Hans'));
        same('20:30', Core\Dates::time('2026-10-24 20:30:00', 'en'));
        Core\Dates::configure('<script>', '7', 'Mars/Base');
        same(['dd MMM yyyy', false, 'UTC'], [Core\Dates::format(), Core\Dates::hour12(), Core\Dates::zone()->getName()]);
        $db = database(); locale($db);
        $settings = new App\Services\AdminSettings($db);
        throws(fn () => $settings->save(['date_format' => 'Y-m-d']), InvalidArgumentException::class);
        throws(fn () => $settings->save(['time_format' => '13']), InvalidArgumentException::class);
        throws(fn () => $settings->save(['timezone' => 'Nowhere/City']), InvalidArgumentException::class);
        $settings->save(['date_format' => 'yyyy-MM-dd', 'time_format' => '12', 'timezone' => 'America/New_York']);
        Core\Dates::load($db);
        same(['yyyy-MM-dd', true, 'America/New_York'], [Core\Dates::format(), Core\Dates::hour12(), Core\Dates::zone()->getName()]);
        same('2026-10-24, 4:30 PM', site_time('2026-10-24 20:30:00'));
        same('—', site_time(null));
    } finally { Core\Dates::configure(); }
});
test('HTTP: pages are gzipped when accepted and have a back-to-top link without scripts', function () {
    $root = appFixture();
    try {
        $db = new Core\Database(['driver' => 'sqlite', 'path' => $root . '/storage/meteor.sqlite']);
        foreach (schemaStatements('sqlite') as $statement) { $db->query($statement); }
        (new App\Services\DemoSeeder($db))->run();
        [$headers, $body] = fixtureRequest($root, '/en/posts', 'GET', [], '', ['HTTP_ACCEPT_ENCODING' => 'gzip, deflate, br']);
        contains('Content-Encoding: gzip', $headers); contains('Vary: Accept-Encoding', $headers);
        $html = (string) gzdecode($body);
        contains('<body id="top"', $html); contains('href="#top" class="back-to-top" title="Back to top"', $html);
        contains('href="/assets/public.css?v=', $html); ok(!str_contains($html, 'admin.css')); ok(!str_contains(strtolower($html), '<script'));
        [$headers, $body] = fixtureRequest($root, '/en/posts');
        ok(!str_contains($headers, 'Content-Encoding')); contains('class="back-to-top"', $body);
    } finally { unset($db); Core\ReleasePackage::removeTree($root); }
});
