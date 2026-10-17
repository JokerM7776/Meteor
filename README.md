# Meteor — Phase 1 foundation

Vanilla PHP 8.1+ with PDO MySQL/SQLite and mbstring. No framework, Composer, npm,
or public JavaScript. Authentication and publishing screens are reserved for later phases.

## Local setup

Copy `config/.env.example` to `.env` in the repository root. Set `DB_DRIVER=sqlite`
for local development, then run:

```sh
php bin/install.php
php -S localhost:8000 -t public public/index.php
```

The installer prompts for credentials, or reads `ADMIN_USER` and `ADMIN_PASS` from
the process environment. Interactive passwords are visibly echoed. It requires an
empty database and seeds only English and the first admin. For MySQL/MariaDB, create
an empty database first and configure its credentials. MySQL DDL implicitly commits;
a failed schema installation can leave partial tables to resolve before retrying.
SQLite schema creation and seed data use transactions. `.env` values are literal,
with quoted strings and comments supported; process environment always wins.

`/` redirects to the default locale and `/en/` renders the placeholder home.
A global `settings` row with key `match_browser_language` and value `1` enables
Accept-Language negotiation. No content or admin routes exist yet.

## Foundation interfaces

- `Database::query/one/all/insert` accept SQL with bound values. `transaction` rolls
  back on exceptions. `upsert(table, values, uniqueColumns)` quotes trusted application
  identifiers and binds values; use `['key', 'locale_scope']` as the settings conflict
  key, with a nullable `locale` in values. `now()` returns a trusted SQL expression.
- `Router::add(method, regex, handler, localized)` takes anchored regexes with named
  captures. Handlers receive `(params, locale)`. `match` returns a 200/404/405 result;
  `dispatch` invokes the handler or sets error headers. GET also supports HEAD.
- `I18n` loads the registry and dictionary once per request. Exactly one live default
  is required. Locale management must maintain that invariant. Preview requires an
  authenticated session; off is always excluded. `__('key', ['name' => 'value'])`
  replaces `{name}` and returns plain text: escape at output with `e()`.
- `Markdown::compile` returns `html`, `toc_html`, and `reading_minutes`. Persist these
  on save in later phases; public rendering must not compile Markdown. Supported:
  headings, paragraphs, emphasis, inline/fenced code, links/images, one nested list
  level, quotes and rules. This is a small subset, not full CommonMark. Raw HTML is
  escaped, unsafe URL schemes are rejected, and code is escaped without highlighting.
- `View::render` accepts an optional layout; `partial` returns buffered HTML. Only
  trusted template output and compiled Markdown may bypass `e()`.
- `Session::login` regenerates the session ID after verified credentials. Later auth
  must call it and implement password/passkey verification and rate limiting.
  `Csrf::requireValid` accepts the token from a form or JSON body and throws code 403
  on failure; future admin controllers must map this to an HTTP 403 response.

## Checks

```sh
php tests/run.php
find . -name '*.php' -not -path './design/*' -print0 | xargs -0 -n1 php -l
bin/build-css.sh
```

The compiled CSS is included for deploys without a build step. Schema tests load
SQLite and compare every table and column against parsed MySQL statements; there
is no MySQL server on this development machine, so MySQL execution is unverified.
