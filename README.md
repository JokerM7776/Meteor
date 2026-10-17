# Meteor

Vanilla PHP 8.1+ with PDO MySQL/SQLite, mbstring, intl, OpenSSL and fileinfo. No framework, Composer, npm,
or public JavaScript. Includes password/passkey authentication and a multilingual admin area.

## Local setup

Copy `config/.env.example` to `.env` in the repository root. Set `DB_DRIVER=sqlite`
for local development, then run:

```sh
php bin/install.php
php bin/seed.php
php -S localhost:8000 -t public public/index.php
```

The installer prompts for credentials, or reads `ADMIN_USER` and `ADMIN_PASS` from
the process environment. Interactive passwords are visibly echoed. It requires an
empty database and seeds only English and the first admin. For MySQL/MariaDB, create
an empty database first and configure its credentials. MySQL DDL implicitly commits;
a failed schema installation can leave partial tables to resolve before retrying.
SQLite schema creation and seed data use transactions. `.env` values are literal,
with quoted strings and comments supported; process environment always wins.

`/` redirects to the default locale. The public site includes locale homes, post archives,
category/tag archives, posts, static pages, RSS feeds and `/sitemap.xml`.
A global `settings` row with key `match_browser_language` and value `1` enables
Accept-Language negotiation. Configure these options in `/admin/languages`.

`php bin/seed.php` adds seven English demo posts, categories, tags and an About page.
`php bin/seed.php --demo-locales` also adds live Chinese and preview Malay, with sample
translations. Reruns update the demo rows by slug without duplicating them; use this
only in development because edited demo content is replaced. Existing locale statuses
are preserved. Preview access requires a valid signed-in admin session (user ID and
matching session version), and preview locales never appear in feeds or SEO alternates.

Settings use keys `site_title`, `home_heading`, `home_intro`, `nav_posts`, `nav_tags`,
`nav_about`, `nav_rss`, `language_label`, and `copyright`. Localized values fall back
to the default locale, then a global row, then the built-in UI text. `language_label`
controls the compact header label; native names remain in translation bars and footers.
Global `posts_per_page` defaults to 10, and `custom_css` is served at `/custom.css`
with a five-minute cache and ETag. The layout links it only when non-empty.

Route registration lives in `app/routes.php`; repositories and public controllers live
under `app/`. Internal URLs are root-relative; `APP_URL` supplies canonical, hreflang,
Open Graph, RSS and sitemap URLs. Locale homes end in `/`; other localized routes
redirect trailing slashes away. Article HTML and TOCs are compiled by the seed/save
path, never by public requests.

## Foundation interfaces

- `Database::query/one/all/insert` accept SQL with bound values. `transaction` rolls
  back on exceptions. `upsert(table, values, uniqueColumns)` quotes trusted application
  identifiers and binds values; use `['key', 'locale_scope']` as the settings conflict
  key, with a nullable `locale` in values. `now()` returns a trusted SQL expression.
- `Router::add(method, regex, handler, localized)` takes anchored regexes with named
  captures. Handlers receive `(params, locale)`. `match` returns a 200/301/404/405 result;
  `dispatch` invokes the handler or sets error headers. GET also supports HEAD.
- `I18n` loads the registry and dictionary once per request. Exactly one live default
  is required. Locale management must maintain that invariant. Preview requires an
  authenticated session; off is always excluded. `__('key', ['name' => 'value'])`
  replaces `{name}` and returns plain text: escape at output with `e()`.
- `Markdown::compile` returns `html`, `toc_html`, and `reading_minutes`. The admin persists these
  on save; public rendering must not compile Markdown. Supported:
  headings, paragraphs, emphasis, inline/fenced code, links/images, one nested list
  level, quotes and rules. This is a small subset, not full CommonMark. Raw HTML is
  escaped, unsafe URL schemes are rejected, and fenced PHP, JS, CSS, HTML, SQL and Bash receive light server-side highlighting.
- `View::render` accepts an optional layout; `partial` returns buffered HTML. Only
  trusted template output and compiled Markdown may bypass `e()`.
- `Session::login` regenerates the session ID after verified credentials. Password and passkey authentication both use it; session versions are verified on every request.
  Admin forms use `csrf_token`; JSON endpoints require `X-CSRF-Token`. Invalid tokens return HTTP 403.

## Checks

```sh
php tests/run.php
find . -name '*.php' -not -path './design/*' -print0 | xargs -0 -n1 php -l
bin/build-css.sh
```

The compiled CSS is included for deploys without a build step. Schema tests load
SQLite and compare every table and column against parsed MySQL statements; HTTP header tests exercise the entrypoint through the local `php-cgi` binary (no
network listener needed). There
is no MySQL server on this development machine, so MySQL execution is unverified.

## Admin and upgrading from Phase 2

For an existing database, back it up and run `php bin/migrate.php` with the same
DB environment as the application. This idempotently adds translation publication
timestamps and backfills published translations. Fresh installations already include
the columns. The migration does not change credentials or content.

Open `/admin/login`. Password sign-in is limited to five failed attempts per
IP and username in fifteen minutes. Passkeys require HTTPS (localhost also works),
`APP_URL` set to the exact origin, and `WEBAUTHN_RP_ID` set to its hostname without
scheme or port. Use Security to add, name, rename or remove passkeys. Passwords remain
available; a password change invalidates other sessions. Registration accepts none
attestation and supports ES256 and RS256. Admin JavaScript is limited to WebAuthn.

Posts and Pages have a per-language matrix, search and pagination. The editor saves
Markdown and compiles HTML/TOC on save; translation publication and shared site
visibility are separate controls. Languages start in Preview. Empty settings and
UI strings fall back to the default locale and then built-in English. Custom CSS
is served as a stylesheet, never injected into HTML.

Cover uploads require PHP fileinfo, writable `public/uploads`, `upload_max_filesize`
of at least `5M`, and `post_max_size` large enough for the file plus form text (for
example `8M`). Supported types are JPEG, PNG and WebP; replacements remove the old
randomly named upload after the database save succeeds.

Anonymous public requests do not start sessions and use a five-minute public cache.
Authenticated public requests use private/no-store; admin requests use no-store,
noindex/nofollow and a self-only script policy. No admin scripts appear on public pages.

Tests include generated EC/RSA WebAuthn ceremonies and CGI admin flows. Physical
passkey devices and a live MySQL server still require deployment smoke testing.
