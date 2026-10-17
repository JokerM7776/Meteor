# Meteor — technical notes

Vanilla PHP 8.2+ with PDO MySQL/SQLite, mbstring, intl, OpenSSL, fileinfo, ext-zip and ext-curl. No framework, Composer, npm,
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
MySQL/MariaDB was verified manually against MariaDB 13 (install, admin flows, updates).

## Admin, authentication and upgrading older databases

For an existing database, back it up and run `php bin/migrate.php` with the same
DB environment as the application. This idempotently adds translation publication
timestamps and backfills published translations. Fresh installations already include
the columns. Migrations also normalize usernames to lowercase; passwords and article content are preserved.

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

## Install a release zip

Download both release assets, verify the zip against its SHA-256 sidecar, and unzip
meteor-<version>.zip. It contains one meteor/ directory. Point Apache/Nginx's
document root at **meteor/public**, never the project root. Apache uses the
included .htaccess; Nginx needs `try_files $uri $uri/ /index.php?$query_string`
and a PHP handler for the entrypoint. Keep configuration, storage and backups
outside the public document root.

Open the site to reach /install. The installer checks PHP >= 8.2, PDO and an
available database driver, OpenSSL, mbstring, zip, curl and writable locations.
Choose MariaDB/MySQL (create an empty database first) or a persistent SQLite file
outside public/. SQLite's parent directory must exist and be writable. Test the
connection, then enter the canonical site URL, title and first admin credentials.
Passwords require at least 12 characters and at most 72 bytes. Database passwords
are never filled back into the page, so re-enter one after testing a connection.

The web and CLI installers use the same service. Installation creates English,
a lowercase admin username, hashed password, migration history, a mode-0600 .env
and storage/installed.lock. It redirects to sign-in; /install then returns 404.
Until both .env and the lock exist, non-asset requests redirect to the installer.
Install promptly before exposing an unconfigured site to other users.

For a database created before the web installer existed, keep its .env and database, run the
migrations, then create the installation marker once:

```sh
php bin/migrate.php
touch storage/installed.lock
```

Do not run the fresh installer against an existing database. If an installation
fails after database creation, inspect the failure before retrying: MySQL DDL
cannot be rolled back, and filesystem writes cannot share a database transaction.

## Online and manual updates

Open **Admin → Updates**. Set a GitHub repository as owner/name; leaving it empty
disables online checking. A private repository may need a fine-grained GitHub
token with repository contents read access. Tokens are stored in the database,
never displayed, and only sent to api.github.com. Blank keeps the saved token;
the remove checkbox clears it.

Check for updates to see the latest version, release date and plain-text notes.
Download and install fetches the latest release again, downloads its matching zip
and mandatory SHA-256 asset, and verifies the checksum. Every URL and redirect
must use HTTPS on api.github.com, github.com, or objects.githubusercontent.com.
Other GitHub asset hosts are deliberately refused; use a manual upload if GitHub
serves an asset from a host outside this configured policy.

Alternatively upload the same release zip (up to 50 MB) and optionally paste its
64-character SHA-256. Set PHP upload_max_filesize=50M and post_max_size=55M
(or higher) and allow enough request time/memory for your hosting environment.
Both online and manual installation require the signed-in admin's current
password. Only install releases you trust: packages contain executable PHP and
their manifest provides integrity checks, not publisher signatures.

CLI updates use filesystem access instead of a web password:

```sh
php bin/update.php /path/to/meteor-0.1.42.zip
php bin/update.php /path/to/meteor-0.1.41.zip --allow-downgrade
```

The updater validates paths, file types, every manifest hash, PHP requirements,
version, entry count (5000) and expanded size (200 MB). Downgrades require the
explicit checkbox or CLI flag. It stages files under storage/updates, takes an
exclusive update lock, enables storage/maintenance.flag, backs up affected code,
replaces files, removes obsolete manifest-listed code, runs pending migrations
and resets OPcache when available. .env, storage data and uploads are preserved.
Public PHP requests return 503 with Retry-After during maintenance; the admin
including Updates remains accessible. Existing cached/static responses may still
be served by your web server or CDN.

The PHP process needs write access to managed application files as well as
storage and its backup/update/log directories. A crash or process kill cannot
run cleanup; inspect storage/logs/updates.log and backups before manually removing
a stale maintenance flag. Keep a separate database backup before each update.

## Backups and rollback

The last five code backups appear on Updates with password-protected Restore
actions. A restore first creates a rescue backup of the affected current files.
On a caught update failure, the updater restores replaced/deleted files, removes
new files, clears maintenance mode and logs the error. Logs live in
storage/logs/updates.log; staging directories are deleted after each attempt.

**Code rollback does not undo database migrations.** SQLite wraps each migration
in a transaction; MySQL DDL implicitly commits. Earlier successful migrations
remain recorded even if a later migration fails. Write migrations to be safe to
retry and compatible with the previous application version. Restore a separate
database backup when a schema change cannot work with the restored code.

If automatic restoration fails, keep the named backup. Backup zips contain
meteor/ code files plus backup.json listing hashes and paths that were absent
before the update. For manual recovery, verify hashes, copy the backed-up code,
remove only paths in that missing list, and preserve .env, storage and uploads.
Reset/restart PHP OPcache and clear maintenance mode after verifying recovery.

## Packaging and GitHub release automation

VERSION starts at 0.1.0. To build locally using the installed standalone
Tailwind v4 CLI:

```sh
bin/build-css.sh
php bin/package.php
```

dist/meteor-<version>.zip includes compiled assets, runtime PHP, schemas,
migrations, source styles, README and LICENSE if present. Storage and uploads
contain only .gitkeep. Tests, designs, drafts, repository metadata, local
environment files and user data are excluded (the safe config/.env.example
template is included). meteor/manifest.json lists SHA-256 hashes for all payload
files; the zip has a separate .sha256 sidecar. The same artifact supports fresh
installs and updates. No build tools are needed on the deployment host.

Enable GitHub Actions on the repository and permit the built-in GITHUB_TOKEN
to write repository contents/releases (organization policies may also apply).
.github/workflows/release.yml runs on pushes to main or manual dispatch. It
sets up PHP 8.3 plus extensions and CGI, lints and runs offline tests, downloads
the **Tailwind v4.3.3 linux-x64 standalone binary**, and rebuilds CSS. It derives
<major.minor from VERSION>.<github.run_number>, writes that version only in the
job workspace, packages, and uses gh release create to tag the checked-out
commit as v<version>, attach the zip/checksum, generate notes and mark it latest.
No personal token or version commit is needed. Increment the major/minor portion
of VERSION when starting a new release series. A rerun has the same run number;
if that release already exists, the create step refuses to overwrite it.

Tests use disposable application copies, SQLite, generated archives and a fake
HttpClient; they make no network requests. Live GitHub publication/downloads and
MySQL execution require deployment smoke testing.
