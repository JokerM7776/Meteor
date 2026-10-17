<?php
test('I18n: registry, default/current/builtin/key fallback and parameters', function () {
    $db = database(); locale($db, 'fr', 'live', 1, 2); locale($db, 'en', 'live', 0, 1);
    locale($db, 'ja', 'preview', 0, 0); locale($db, 'de', 'off', 0, 3);
    foreach ([['en', 'greeting', 'Hello {name}'], ['fr', 'greeting', 'Bonjour'], ['fr', 'default_only', 'Repli'], ['en', 'empty', '']] as [$code, $key, $value]) {
        $db->upsert('ui_strings', compact('key', 'value') + ['locale' => $code], ['locale', 'key']);
    }
    $i18n = new Core\I18n($db, ['builtin' => 'Built in', 'empty' => 'fallback']);
    same(['en', 'fr'], $i18n->enabledLocales()); same(['ja', 'en', 'fr'], $i18n->enabledLocales(true));
    same('fr', $i18n->defaultLocale()); same('Bonjour', $i18n->translate('greeting'));
    $i18n->setLocale('en'); same('Hello Ada', $i18n->translate('greeting', ['name' => 'Ada']));
    same('Repli', $i18n->translate('default_only')); same('Built in', $i18n->translate('builtin'));
    same('unknown', $i18n->translate('unknown')); same('', $i18n->translate('empty'));
    throws(fn () => $i18n->setLocale('ja')); $i18n->setLocale('ja', true);
    throws(fn () => $i18n->setLocale('de', true)); same('ltr', $i18n->direction());
    $GLOBALS['i18n'] = $i18n; same('Built in', __('builtin'));
});
test('I18n: Accept-Language ranking, regional lookup and live-only selection', function () {
    $db = database(); locale($db); locale($db, 'fr', 'live', 0); locale($db, 'pt-BR', 'live', 0);
    locale($db, 'ja', 'preview', 0); locale($db, 'de', 'off', 0);
    $i18n = new Core\I18n($db, []);
    foreach (['fr-CA, en;q=0.8' => 'fr', 'en;q=0.2,fr;q=0.9' => 'fr', 'PT-br' => 'pt-BR', 'pt' => 'pt-BR', 'ja,de' => 'en', '*;q=0.1,fr;q=0.8' => 'fr', 'fr;q=0,en;q=1' => 'en', 'en;q=0,*;q=0.5' => 'fr', 'fr;q=4' => 'en', '' => 'en'] as $header => $expected) {
        same($expected, $i18n->negotiate($header));
    }
});
test('I18n: invalid defaults fail clearly', function () {
    $db = database(); throws(fn () => new Core\I18n($db, []), RuntimeException::class);
    locale($db); locale($db, 'fr', 'live', 1);
    throws(fn () => new Core\I18n($db, []), RuntimeException::class);
});
