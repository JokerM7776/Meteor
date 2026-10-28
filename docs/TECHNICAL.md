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
only in development because edited demo content is replaced. Languages the installer added
as Off are switched to the demo status; other existing statuses are preserved. Preview access requires a valid signed-in admin session (user ID and
matching session version), and preview locales never appear in feeds or SEO alternates.

Per-language settings are `site_title`, `site_tagline`, `footer_text`, `copyright`,
`language_label` and `footer_menu_heading` (Admin → Settings → Site), `home_eyebrow`,
`home_heading`, `home_intro` and `home_button_{1,2}_label` (Settings → Home page), and
`author_name` / `author_bio` (Settings → Author). Each tab edits them as one table (rows = keys,
columns = languages); a save changes only the cells it posts, and an empty cell deletes that
row. The copyright tokens `{year}` (current UTC year) and `{site}` (site title) are replaced;
the default copyright is `© {year} {site}`. Footer text, the home intro and the home eyebrow
default to nothing; the home heading defaults to the site title and the intro to the tagline.
Home globals: `home_sections` (`intro:1,pinned:1,latest:1,categories:0,author:0`, order =
display order), `home_latest_count`, `home_decoration`, `home_button_{n}_url|_show`.
Author globals: `author_avatar` (media library URL) and `author_links` (one URL or
`Label | URL` per line, max 8). The author card is a representative h-card: `p-name u-url u-uid`
links to the language's home page with `rel="me"`, profile links are `u-url rel="me"`, mailto
links `u-email`; footer setting `footer_brand` = `site` | `author` | `none`.
Appearance globals: `theme_scheme` (`auto`|`light`|`dark`) sets `<html data-theme>`, which drives
Tailwind's `dark:` variant (`auto` follows `prefers-color-scheme`); `theme_switch` adds a header
menu that links to `/theme?set=…&back=…` (sets the `meteor_theme` cookie, then redirects to a
same-site path). `theme_accent` (a Tailwind color name or `#rrggbb`) and `theme_neutral` replace the
`--color-orange-*` and `--color-slate-*` variables through `/theme.css?v=<hash>`, linked only when
not the default. Localized values fall back
to the default locale, then a global row, then the built-in UI text. `language_label`
controls the compact header label; native names remain in translation bars and footers.
Global `posts_per_page` defaults to 10, and `custom_css` is served at `/custom.css`
with a five-minute cache and ETag. The layout links it only when non-empty.

Global identity settings are `site_icon`, `header_show_icon`, `header_show_title`,
`header_show_switcher`, `footer_show_languages`, `footer_show_feeds`, `footer_show_site`
(the footer menu column) and `footer_show_copyright`. Toggles default on; at least the header
icon or title must remain visible.
The site icon, post covers and the author photo are chosen from the media library (stored as
`/uploads/media/<name>` URLs and validated against the `media` table; icons and photos must be
PNG/JPG/WebP/GIF). Media files are never deleted by these fields; older icons/covers uploaded
straight to `/uploads/<hex>.<ext>` keep working and are deleted when replaced. The media delete
page counts these uses. The default icon is `/assets/favicon.svg`.
Header and footer menus live in `menu_items` (menu `header`|`footer`, kind, url,
sort_order, is_visible) and `menu_item_labels` (item, locale, label). Each menu holds
every built-in kind once — `posts`, `tags`, `pages` (expands to published pages
with “Show in navigation”), `rss` (hidden on preview locales), `sitemap` — which can be
hidden and reordered but not removed, plus up to 30 rows in total including custom
`link` rows. Built-in labels fall back to the UI string in that language; link labels
fall back to the default language, and a link with neither is not shown. `{locale}` in
a link URL becomes the current language code. Only single-slash root paths, fragments,
HTTP(S) and mailto are allowed (`SiteLinks::validUrl`), checked on save and on render.
A `link` row with `page_id` links to a page: it is shown once the page is published in that
language, its label defaults to the page's navigation label or title, and it is skipped when the
same menu's `pages` item already lists the page. The installer creates a draft About page
(slug `about`) linked from both menus; migration `006` converts the old built-in `about` items to
such page links (creating the draft page when missing) and moves customized `home_heading`,
`home_intro` and `footer_description` interface text into the site settings.
Admin → Header & footer edits both menus as tables (one column per language); rows and
languages missing from a post are left unchanged. Migration `003_menus` creates the tables
and converts the earlier `nav_show_*`, `nav_*` label and `header_links`/`footer_links`
settings. Categories & tags are also tables: each row is its own form (HTML `form`
attribute), so saving a row never posts the rest; deletion goes through a confirmation page.

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
- *Keep me signed in* (`Core\Remember`): the cookie `<SESSION_NAME>_remember` (HttpOnly, SameSite=Lax, Secure on HTTPS, 30 days) holds `selector:secret`; `remember_tokens` stores the selector, the SHA-256 of the secret, the user's `session_version`, the user agent and the last use. When no session is signed in, `bootstrap.php` signs in from the cookie. A wrong secret deletes the row; a changed `session_version` (new password, *Sign out everywhere else*, `bin/sign-out.php`, `bin/reset-password.php`) makes every row useless. A session started from a row keeps `remember_id`, and `Auth::user()` ends it when the row is gone (device signed out on the Security page).
- CAPTCHA (`App\Services\CaptchaSettings`, `Core\Captcha`): keys for Cloudflare Turnstile and Google reCAPTCHA v2 are stored side by side; `captcha_provider` picks the one used for both the sign-in page (`turnstile_enabled`, a name kept from older versions) and comments (`comments_captcha`). Older sites without `captcha_provider` fall back to `comments_captcha_provider`. `CAPTCHA=off` (or `TURNSTILE=off`) in `.env` skips the sign-in check only. Turning the sign-in check on verifies the secret with a dummy token (`invalid-input-secret`).
- Secret sign-in address (`App\Services\LoginPath`, setting `login_path`, empty by default): when set, `/<login_path>` serves the sign-in form and records `login_gate` in the session, which opens the two passkey sign-in endpoints. For signed-out visitors without that mark, `bootstrap.php` sets `$adminRequest` to false before any session starts, so `/admin/*` gets the public router's 404 page with public headers and no cookie, the same as any missing URL. Paths are 8–64 of `[a-z0-9_-]`, must not look like a language code or match a file in `public/`. `bin/login-path.php` shows, sets or clears it.
  Admin forms use `csrf_token`; JSON endpoints require `X-CSRF-Token`. Invalid tokens return HTTP 403.

## Checks

```sh
php tests/run.php
find . -name '*.php' -print0 | xargs -0 -n1 php -l
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

Open `/admin/login` (or the secret sign-in address set on the Security page). Password sign-in is limited to five failed attempts per
IP and username in fifteen minutes. Passkeys require HTTPS (localhost also works),
`APP_URL` set to the exact origin, and `WEBAUTHN_RP_ID` set to its hostname without
scheme or port. Use Security to add, name, rename or remove passkeys. Passwords remain
available; a password change invalidates other sessions. Registration accepts none
attestation and supports ES256 and RS256. Admin JavaScript is limited to WebAuthn.

Posts and Pages have a per-language matrix, search and pagination. The editor saves
Markdown and compiles HTML/TOC on save; translation publication and shared site
visibility are separate controls. Languages start in Preview. Empty settings fall
back to the default locale and then built-in English. Custom CSS is served as a
stylesheet, never injected into HTML.

Interface text has three layers: site edits in `ui_strings` → the language file
`lang/<code>.json` → the default language → `lang/en.php` → the key. `lang/en.php` lists
only keys the public pages use, each in one group of `App\Services\UiStrings::GROUPS`.
A language file is JSON (never PHP, so importing cannot run code):
`{"meteor_language_pack": 1, "code", "name", "native_name", "direction", "strings": {key: text}}`,
up to 256 KB; unknown keys and texts whose `{placeholders}` differ from English are
skipped (`Core\LanguagePack`). Admin → Settings → Interface text edits all languages in one
table; a cell equal to the language file is not stored, so file improvements still arrive
with updates, and cells that were not posted (or did not change) are left alone. The admin
script posts only edited cells; the server refuses a post that reaches `max_input_vars`.
Languages → *Download file* exports a language's own text; *Import a language file* stores
the differences as site edits and adds an unknown language as Off.
Bundled files are added as **Off** languages once: by the installer, by migration `004`,
and after every update/`bin/migrate.php` (`App\Services\LanguagePacks::sync`). Offered codes
are remembered in the global setting `language_packs_known`, so a deleted language is not
re-added. `{language}` placeholders receive the language's native name.

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
document root at **meteor/public** where possible. Apache uses the included
public/.htaccess; Nginx needs `try_files $uri $uri/ /index.php?$query_string`
and a PHP handler for the entrypoint. Keep configuration, storage and backups
outside the public document root.

For shared hosting the unzipped meteor/ folder may itself be the Apache document
root: the root .htaccess rewrites every path to public/<path>, so only public/ is
reachable and public/.htaccess takes over from there. Without mod_rewrite it denies
everything except the root index.php, which is never part of the app: it only
renders views/setup.php, a static help page. Nginx ignores .htaccess, so
the root index.php also tells Nginx users that files are exposed and the root must
be public/. public/index.php shows the same page (variant `subfolder`) when
SCRIPT_NAME shows it is mounted under a sub-path, because every URL is root-relative.
The root index.php and .htaccess are managed release files, so updaters before this
change reject the new zips ("Unsafe manifest path"); update those installs once by
unzipping over them.

Open the site root (/) to reach the installer; every other URL redirects there until
Meteor is installed. It lives at / rather than /install because host firewalls such as
Imunify360 commonly block /install paths. The installer checks PHP >= 8.2, PDO and an
available database driver, OpenSSL, mbstring, zip, curl and writable locations.
Choose MariaDB/MySQL (create an empty database first) or a persistent SQLite file
outside public/. SQLite's parent directory must exist and be writable. Test the
connection, then enter the canonical site URL, title and first admin credentials.
Passwords require at least 12 characters and at most 72 bytes. Database passwords
are never filled back into the page, so re-enter one after testing a connection.

The web and CLI installers use the same service. Installation creates English,
a lowercase admin username, hashed password, migration history, a mode-0600 .env
and storage/installed.lock. It redirects to sign-in; / then serves the site.

Install from .env (no form): when .env sets ADMIN_USER and ADMIN_PASS, the first GET of /
installs with the .env database settings, APP_URL (else the request's address) and
SITE_TITLE (default "Meteor Blog"), removes ADMIN_USER and ADMIN_PASS from .env and redirects to
sign-in. If it fails, the form is shown with the error.
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

## Media library and editor

Images live in `public/uploads/media/` under random 32-hex names (`MediaLibrary`); the
`media` table keeps the original name, size, alt text, category and a lower-cased
`search_text`, and `media_tags` the tags. Search ANDs each word against `search_text`
with `LIKE … ESCAPE '!'`, so it behaves the same on MySQL and SQLite and ignores case
for non-ASCII text. Lists are paginated (20 rows in Admin → Media, 24 in the picker).

`Core\ImageOptimizer` uses GD: it applies the JPEG EXIF orientation, shrinks to the
configured longest side, re-encodes to WebP or AVIF (dropping all metadata) and writes a
400 px WebP thumbnail. Animated GIFs, images over 60 megapixels or beyond the memory
limit are kept as uploaded. Settings (`media_optimize`, `media_format`, `media_quality`,
`media_max_size`) are global rows in `settings`; optimization defaults to on.

The editor (`/assets/admin.js`) keeps the Markdown textarea as the source of truth. Visual
mode renders it with a JavaScript copy of the `Core\Markdown` subset and, only after an
edit, serializes the editable HTML back to Markdown on mode switch or submit. Pasted and
dropped content is inserted as plain text. The picker reads `GET /admin/media/library`
(JSON) and uploads to `POST /admin/media/upload` (multipart with the CSRF field).

## Online and manual updates

Open **Admin → Updates**. Online updates always come from the public official
repository `JokerM7776/Meteor` (`Core\GitHubUpdates::REPOSITORY`); no token is
sent. Migration `004` removes the old `update_repository` / `update_token` settings.
Manual zip upload stays available for any trusted release.

Check for updates to see the latest version, release date and plain-text notes.
Download and install fetches the latest release again, downloads its matching zip
and mandatory SHA-256 asset, and verifies the checksum. Every URL and redirect
must use HTTPS on api.github.com, github.com, or objects.githubusercontent.com.
Other GitHub asset hosts are deliberately refused; use a manual upload if GitHub
serves an asset from a host outside this configured policy.

Alternatively upload the same release zip (up to 50 MB) and optionally paste its
64-character SHA-256. Set PHP upload_max_filesize=50M and post_max_size=55M
(or higher) and allow enough request time/memory for your hosting environment.
Installing (online or manual) needs only the signed-in admin session and CSRF
token; restoring a backup also asks for the current password. Only install releases you trust: packages contain executable PHP and
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

The last five code backups appear on Updates as a table (version, date, size).
Restore opens a confirmation page that asks for the current password (rate-limited
like sign-in). A restore first creates a rescue backup of the affected current files.
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

VERSION holds the major and minor version with a .0 patch (for example 0.2.0).
Each push to main releases major.minor.N, where N counts the commits since VERSION
last changed: changing VERSION to 0.3.0 releases 0.3.0, and the pushes after it
0.3.1, 0.3.2 and so on. To build locally using the installed standalone
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

## Content importers

`app/Services/Import/` implements Hexo and Typecho ingestion with no schema changes.
Sources parse into normalized item arrays; `Writer` validates and compiles Markdown with
`ContentEditor::compile()` before inserting masters and translations. Groups share a master
only within the current run. The target language is the base when present. Existing slugs
are skipped or renamed. An untouched installer About page is replaceable only when every
translation still matches the default title, body, empty summary and draft status. Terms reuse language names
or slugs. Check uses in-memory slug, term and image reservations, and makes no database or
media writes. Reports include item results, warnings, totals and comment status counts.

Each translation is stored in its own transaction. MediaLibrary has its own transaction,
so new images are stored immediately before the content transaction and removed if it fails.
Images are deduplicated by SHA-1 of source bytes for the run, imported via temporary files
under storage, and rewritten to media URLs. Temporary files are deleted in finally blocks.
A successful earlier translation survives a later translation failure. Check validates
image formats without running the optimizer or testing disk write permissions.

Zip entries are read in memory, never extracted. Both archives and folders have limits of
20,000 entries and 512 MB uncompressed, with 5 MB per Markdown file and MediaLibrary's
15 MB image limit. Traversal paths, absolute paths, dotfiles, excluded build directories
and directory symlinks are skipped. Archives require ext-zip; folders do not. HTML uses
DOMDocument with LIBXML_NONET, falls back to stripped text without ext-dom, and never
passes raw HTML to public templates. Markdown is compiled on import.

Typecho uses a separate PDO connection and SELECT statements only. SQLite headers are
validated and files opened with SQLite's read-only flag. Admin SQLite uploads receive random
names in private `storage/imports/<32-hex-token>/` directories. Successful admin Checks
retain the source zip, SQLite file and uploads zip for up to two hours; session state contains
only the token, source and whitelisted options. Import/Cancel verifies the session token and
deletes the directory. Every visit prunes expired directories. Files use mode 0600 and
directories 0700. MySQL passwords must be entered again and never enter saved state. MySQL uses utf8mb4 and a
five-second connection timeout. Table prefixes are validated against
`/^[A-Za-z0-9_]{0,32}$/D` before composing identifiers. Attachment metadata is inspected by a
path regex, never unserialize. Connection errors do not expose credentials; passwords are
not saved in forms, sessions, logs or reports. Comment IPs are hashed with Meteor's existing
app secret, or left empty when none exists. Imports never create a secret during Check.

Reference links are resolved outside code before HTML conversion and media rewriting.
Used definitions are removed; unused definitions and footnotes stay. Same-language slug
collisions become distinct groups, so skip/rename applies instead of discarding translations.
Only successfully resolved media-library URLs are stored as covers; unresolved covers become NULL.
