<?php
declare(strict_types=1);
// Theme API v1 helpers for public templates (see docs/THEMES.md). e(), asset(), url() and absolute_url() live in helpers.php.

/** Interface text in the page's language. */
function t(string $key, array $params = []): string { return __($key, $params); }

/** A partial from the active theme, else Meteor's. It sees $site, $page, $theme and the given $data. */
function partial(string $name, array $data = []): string { return App\Theme\Context::partial($name, $data); }

/** A theme's slot (slots/<name>.php), or '' when the active theme has none. */
function slot(string $name): string { return App\Theme\Context::partial($name, [], true); }

/** Escaped, localized list of language names ("A, B and C"), each marked with its lang. $languages: list of Language. */
function language_names(array $languages, int $max = 3): string
{
    return language_list(array_map(fn ($l) => ['code' => $l->code, 'native_name' => $l->nativeName], $languages), $max);
}
