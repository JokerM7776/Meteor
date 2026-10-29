# Meteor Blog

A fast, multilingual blog in plain PHP. Public pages are server-rendered HTML and CSS with
**zero JavaScript**; a small admin area handles writing, translating and site settings.

- Write once, translate into any number of languages, each with its own URL (`/en/…`, `/zh-hans/…`).
- Languages are managed in the admin: **Live**, **Preview** (editors only) or **Off**.
- Sign in with a **passkey** (Touch ID, Face ID, Windows Hello, security key) or a password.
- RSS per language, a multilingual sitemap with hreflang, dark mode, and one-click updates.
- No framework, no Composer, no Node — upload the files and open the site.

> Deep technical notes (architecture, security model, update internals) live in
> [docs/TECHNICAL.md](docs/TECHNICAL.md).

---

## 1. Requirements

| | |
|---|---|
| PHP | **8.2 or newer** with `pdo`, `pdo_mysql` and/or `pdo_sqlite`, `openssl`, `mbstring`, `intl`, `fileinfo`, `zip`, `curl`; `gd` with WebP for image optimization (optional) |
| Database | **MariaDB 10.5+ / MySQL 8.0.16+** (recommended) or **SQLite 3** |
| Web server | Apache with `mod_rewrite`, or Nginx + PHP-FPM |
| HTTPS | Required for passkeys on a real domain (`localhost` works without it) |

Check PHP with `php -v` and `php -m`.

## 2. Install from a release (recommended)

1. Download `meteor-<version>.zip` (and its `.sha256`) from the
   [Releases](../../releases) page. Optionally verify it:
   `shasum -a 256 -c meteor-<version>.zip.sha256`
2. Unzip it on your server. You get one `meteor/` folder.
3. Point the domain's document root at that folder:
   - **Best:** use **`meteor/public`** as the document root
     (see [Web server configuration](#5-web-server-configuration)).
   - **Shared hosting (Apache):** if you can't choose `public/`, use `meteor/` itself, or move
     its contents (including the hidden `.htaccess`) into `public_html`. The root `.htaccess`
     sends every request into `public/`, so nothing else can be downloaded. It needs
     `mod_rewrite` and `AllowOverride All`; without them you get a setup help page instead.
   - Meteor must be the root of a domain or subdomain, not a sub-folder such as
     `example.com/meteor/`. A help page is shown if it is opened from a sub-folder.
   - **Nginx** never reads `.htaccess`, so there the document root **must** be `meteor/public`.
4. If you use MariaDB/MySQL, [create an empty database and user](#3-create-the-database).
5. Open your site in a browser. Until Meteor is installed, the home page is the installer, which:
   - checks PHP requirements and folder permissions,
   - asks for the database (MariaDB/MySQL or SQLite) with a **Test connection** button,
   - asks for the site URL and title,
   - asks you to **choose the admin username and password** (12+ characters).
6. Sign in at **`/admin/login`**. The installer locks itself afterwards.

   **Form blocked by your host?** (for example a bare "403 Forbidden" from a firewall such as
   Imunify360). Install from a file instead: copy `config/.env.example` to `.env` next to
   `VERSION`, fill in `APP_URL` and the database settings, uncomment `ADMIN_USER` and `ADMIN_PASS`
   with your chosen login, and open the site once. Meteor installs itself, deletes `ADMIN_USER`
   and `ADMIN_PASS` from `.env`, and sends you to the sign-in page.

> **There is no default username or password.** You choose them during installation.
> Forgot the password? See [Reset an admin password](#8-reset-an-admin-password).

## 3. Create the database

**MariaDB / MySQL** — run as the database administrator (`mariadb -u root -p` or `mysql -u root -p`),
using your own strong password:

```sql
CREATE DATABASE meteor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'meteor'@'localhost' IDENTIFIED BY 'choose-a-strong-password';
GRANT ALL PRIVILEGES ON meteor.* TO 'meteor'@'localhost';
FLUSH PRIVILEGES;
```

Then enter host `127.0.0.1` (or `localhost`), port `3306`, database `meteor`, user `meteor`
and that password in the installer.

**SQLite** — nothing to create. Choose SQLite in the installer and keep the default path
`storage/meteor.sqlite` (outside the public folder). The `storage/` folder must be writable.

## 4. Configuration (`.env`)

The installer writes `.env` in the project root (permissions `600`). To configure by hand,
copy the template and edit it:

```sh
cp config/.env.example .env
```

| Variable | Example | Meaning |
|---|---|---|
| `APP_URL` | `https://blog.example.com` | Exact public origin, no trailing slash. Used for canonical URLs, RSS, sitemap and passkeys. |
| `APP_ENV` | `production` | `development` shows more detail in logs. |
| `DB_DRIVER` | `mysql` or `sqlite` | Database type (`mysql` also covers MariaDB). |
| `DB_HOST`, `DB_PORT` | `127.0.0.1`, `3306` | MariaDB/MySQL server. |
| `DB_NAME`, `DB_USER`, `DB_PASS` | `meteor`, `meteor`, `…` | MariaDB/MySQL credentials. |
| `DB_PATH` | `storage/meteor.sqlite` | SQLite file, relative to the project root. |
| `SESSION_NAME` | `meteor_session` | Admin session cookie name. |
| `CAPTCHA` | `on` | `off` skips the CAPTCHA on the sign-in page (after a wrong key locks you out). |
| `WEBAUTHN_RP_ID` | `blog.example.com` | Passkey domain: the hostname of `APP_URL`, no scheme or port. |
| `WEBAUTHN_RP_NAME` | `Meteor Blog` | Name shown in the passkey prompt. |

Environment variables set by the server override `.env`. **Never commit `.env`** — it is in
`.gitignore`, and only the template `config/.env.example` belongs in git.

## 5. Web server configuration

**Apache** — `public/.htaccess` is included (needs `AllowOverride All` and `mod_rewrite`). A root
`.htaccess` is also included, so `DocumentRoot /var/www/meteor` works too when `public/` can't be
chosen; the setup below is still preferred:

```apache
<VirtualHost *:443>
    ServerName blog.example.com
    DocumentRoot /var/www/meteor/public
    <Directory /var/www/meteor/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**Nginx + PHP-FPM:**

```nginx
server {
    listen 443 ssl;
    server_name blog.example.com;
    root /var/www/meteor/public;
    index index.php;

    location / { try_files $uri /index.php?$query_string; }

    # Uploaded files must never run as PHP.
    location ~* ^/uploads/.*\.(php\d?|phtml|phar)$ { deny all; }

    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location ~ \.php$ { deny all; }
}
```

The PHP user needs write access to `storage/`, `public/uploads/`, `.env` (during install) and —
for online updates — the application files. For photos allow `upload_max_filesize = 16M` and
`post_max_size = 20M` (media uploads go up to 15 MB); for uploading update zips allow `upload_max_filesize = 50M` and `post_max_size = 55M`.

## 6. Using Meteor

- **Admin:** `/admin` — Posts (translation matrix), Pages, **Comments**, **Media**, Categories & tags, **Languages**,
  **Header & footer**, Settings, **Security**, **Updates**.
- **Add a language:** Admin → Languages → *Add a language*. It starts in **Preview**; translate,
  then click **Go live**. English is the default language.
- **Posts and pages lists:** sort by last update, newest, oldest, title or (posts) publish date;
  20 per page with page numbers. *View ↗* opens the public page. In the editor, pick the language
  from the menu next to Save; the slug is generated from the title when left empty; a post's
  cover is chosen from the media library.
- **Dates:** Shared settings → *Publish date* sets the date shown on a post (e.g. for posts
  moved from another blog); a future date schedules it. Left empty, it is set when the post is
  first published. Pages have a *Date* too. Settings → Site → *Date & time* picks the time zone
  (used by the site and this field), the date format (e.g. `25 Oct 2026`, `Oct 25, 2026`,
  `2026-10-25`, or each language's own style) and a 12- or 24-hour clock. Times are stored in UTC.
- **Writing:** the editor has a **Visual** mode with a toolbar (headings, bold, italic, links,
  lists, quotes, code, images) and a **Markdown** mode that shows the `#`, `**`, `-` syntax. Both
  edit the same text; the toolbar works in either mode.
- **Extended Markdown:** tick *Extended Markdown* in the editor's Shared settings to add
  [extended syntax](https://www.markdownguide.org/extended-syntax/) to a post or page: tables,
  footnotes `[^1]`, heading ids `## Title {#id}`, definition lists, task lists `- [x]`,
  `~~strikethrough~~`, `==highlight==`, `H~2~O`, `X^2^`, emoji shortcodes `:tada:`, automatic links
  and `\` escapes. It applies to every language of that item. While it is ticked, a second toolbar
  row inserts strikethrough, highlight, sub- and superscript, task lists, tables, footnotes,
  definition lists and emoji; Visual mode shows the first four formatted and the rest as text.
  To start every new post and page with it ticked, use Settings → Site → *Enable Extended Markdown*.
- **Social sharing:** links shared on social media or in chat apps show the post's cover
  (Open Graph and Twitter card tags; a 2:1 cover around 2400 × 1200 px works best). Settings →
  Site → *Social sharing* sets a default image (1200 × 630 px) and whether to use it for posts
  without a cover and for every other page.
- **Search:** a search icon in the header opens `/<language>/search`, which finds published posts
  and pages in that language (every word must match). No JavaScript: results load as a normal
  page. Turn it off under Header & footer → *Show search*.
- **Comments:** Admin → Comments → *Settings* → *Allow comments on posts*. Each post can turn its
  comments off in the editor (Shared settings → *Allow comments*). Each language of a post has its
  own discussion. Visitors can reply to a comment (one level); you can reply from the admin, shown
  with an “Author” badge. New comments are held for approval, published when the email already has
  an approved comment, or published at once — your choice. The sidebar shows how many are waiting.
  - *Spam protection without JavaScript:* a hidden trap field, a signed form that must be a few
    seconds old, at most 5 comments per visitor per 10 minutes, and comments with more than two
    links always wait for approval. Visitor IPs are stored only as a keyed hash.
  - *CAPTCHA (optional, on by default once keys exist):* uses the site's CAPTCHA from Security
    (see below). The check opens on a separate page after the visitor presses Send, so post pages
    stay script-free.
- **Webmentions:** while comments are on, Meteor accepts [Webmentions](https://www.w3.org/TR/webmention/)
  at `/webmention` and advertises it on every post. Replies show with the comments; likes,
  reposts, bookmarks and mentions are listed under *Mentions*. All follow the moderation setting.
  Turn on *Send webmentions* to notify sites your published posts link to. Meteor fetches other
  sites only over http(s) on standard ports and never to private or local addresses.
  - *Background work:* received webmentions are checked, and outgoing ones sent, right after the
    response on PHP-FPM and LiteSpeed. For retries and scheduled posts, and on other servers, add a
    cron job (shown with your real path in Comments → Settings):
    ```
    */10 * * * * php /path/to/meteor/bin/cron.php
    ```
    The cron job also deletes spam older than 30 days. Without cron, use *Process now* in
    Comments → Settings. Sending needs the PHP `curl` extension.
- **Media:** Admin → Media, or *Image* in the editor toolbar. Uploads get random file names, so
  their addresses cannot be guessed. Give images a category, tags and alt text when uploading or
  later; search and filter by them. Admin → Settings → *Optimization* (on by default) converts
  uploads to WebP or AVIF, shrinks very large photos and removes camera data such as GPS location.
- **Customize the site:** Admin → Settings — pick the site icon from the media library and edit
  the title, tagline, footer text and copyright for every language in one table.
  - *Home page:* choose and drag-reorder the sections (intro, pinned posts, latest posts,
    categories, author), write the intro per language, and set up to two buttons (or none).
  - *Author:* your name, bio, photo and profile links, published as a representative
    [h-card](https://microformats.org/wiki/representative-h-card-authoring) in the footer and/or
    on the home page.
  - *Appearance:* light, dark or follow the device; an optional visitor switch (no JavaScript);
    accent color (presets or any color) and the text/background tone.
  - New sites start with the footer line “Powered by Meteor Blog”; change or remove it in the
    site text table (Copyright). Otherwise the defaults use your site title.
- **Interface text and language files:** Admin → Settings → *Interface text* — every menu label,
  button and heading in one table, one column per language. Meteor ships language files for
  简体中文 (`zh-hans`) and Bahasa Melayu (`ms`) in `lang/`; they start **Off** — switch one to Preview or
  Live under Languages. Languages → *Download file* exports a language as JSON to share with
  another site, and *Import a language file* brings one in (a new language is added as Off).
- **Get languages:** Languages → *Get languages* → *Check for languages* reads the community
  language catalog on GitHub (`lang/` on `main`). *Add* downloads a language (added as Off);
  *Update* brings newer text for one you have. Downloads are kept in `storage/lang/`, your own
  edits always stay on top, and Meteor only contacts GitHub when you click the button. Sites that
  used `zh` before keep that code and its URLs and read the `zh-hans` file.
  To translate Meteor into your language, see [CONTRIBUTING.md](CONTRIBUTING.md#translating-meteor).
- **Header and footer:** Admin → Header & footer — drag to reorder, show or hide the built-in
  links, add links to pages or any URL with a label per language, and choose what the footer
  shows (site info, author card or nothing; each column and the copyright line can be turned off).
  To put a page in a menu, add it there (a page link uses the page title unless you write a
  label). Tick *New tab* to open a link in a new tab; *Show ↗ on links that open in a new tab*
  adds the icon. The About link points at a normal page (Pages → About), so its content is yours
  to edit.
- **Categories & tags:** one table row per term with a column per language, so missing
  translations stand out; each row saves on its own.
- **Passkeys:** Admin → Security → *Add a passkey*. Requires HTTPS (or `localhost`) and correct
  `APP_URL` / `WEBAUTHN_RP_ID`. Password sign-in always stays available as a fallback.
- **Change username or password:** Admin → Security. Changing the password signs out other sessions.
- **CAPTCHA:** Admin → Security → *CAPTCHA*. Save keys for Cloudflare Turnstile and/or Google
  reCAPTCHA v2 and choose the one the whole site uses: on the password form of the sign-in page
  (switch on there; passkeys skip it) and for comments (switch on in Comments → Settings).
  Locked out by a wrong key? Sign in with a passkey, or add `CAPTCHA=off` to `.env`.
- **Keep me signed in:** tick it on the sign-in page to stay signed in on that browser for 30
  days. Admin → Security → *Signed-in devices* lists these browsers; sign out one, or
  *Sign out everywhere else* (this also ends sessions that did not tick the box).
- Failed sign-ins are limited to 5 per 15 minutes per IP and username.

## 7. Updating

- **Online:** Admin → Updates → *Check for updates* → *Download and install*. Releases come from the
  official repository, [github.com/JokerM7776/Meteor](https://github.com/JokerM7776/Meteor/releases),
  and the checksum is verified.
- **Manual:** Admin → Updates → *Upload update zip*, or from the shell:
  `php bin/update.php /path/to/meteor-<version>.zip`

Every update backs up the current code first (the last 5 backups are listed on the same page;
restoring one asks for your password), shows visitors a short maintenance page, runs database migrations, and **never touches
`.env`, `storage/` or `public/uploads/`**. Code restores do not undo database changes, so
**back up your database before updating**.

## 8. Reset an admin password

From the server shell, in the project folder:

```sh
php bin/reset-password.php <username>
```

It asks for the new password (12+ characters), signs out every session and remembered browser,
and clears the failed-login counter. Use `NEW_PASSWORD='…' php bin/reset-password.php <username>` in scripts.

To sign out every device without changing a password (e.g. after losing a laptop):

```sh
php bin/sign-out.php              # every admin
php bin/sign-out.php <username>   # one admin
```

Every session and every “Keep me signed in” browser ends; sign in again with a password or passkey.

If you moved the sign-in page to a secret address (Security → *Sign-in address*) and forgot it:

```sh
php bin/login-path.php            # show the sign-in address
php bin/login-path.php --clear    # move sign-in back to /admin/login
```

## 9. Local development

```sh
git clone <your-repo-url> meteor && cd meteor
cp config/.env.example .env            # then set DB_DRIVER=sqlite for the quickest start
ADMIN_USER=admin ADMIN_PASS='choose-a-dev-password' php bin/install.php
php bin/seed.php --demo-locales        # optional demo posts (+ zh live, ms preview)
php -S localhost:8000 -t public public/index.php
```

Open <http://localhost:8000/> and sign in at <http://localhost:8000/admin/login> with the dev
credentials you just chose. Use `localhost` (not `127.0.0.1`) so passkeys work.

| Task | Command |
|---|---|
| Run tests | `php tests/run.php` |
| Lint PHP | `find . -name '*.php' -print0 \| xargs -0 -n1 php -l` |
| Rebuild CSS | `bin/build-css.sh` (needs the [Tailwind v4 standalone CLI](https://tailwindcss.com/blog/standalone-cli), e.g. `brew install tailwindcss`) |
| Run migrations | `php bin/migrate.php` |
| Build a release zip | `php bin/package.php` → `dist/meteor-<version>.zip` |

The compiled `public/assets/public.css` and `public/assets/admin.css` are committed, so servers
never need Tailwind. Public pages load only `public.css` (built from `src/public.css` +
`src/base.css`); admin pages and the installer load only `admin.css`. Pages are sent gzipped when
the browser accepts it, and `public/.htaccess` asks Apache to compress CSS and JavaScript.

## 10. Releases (GitHub Actions)

`.github/workflows/release.yml` runs on every push to `main`: it lints, runs the tests,
rebuilds the CSS, packages `meteor-<major.minor>.<run number>.zip` with its `.sha256`, and
publishes a GitHub Release tagged `v<version>`. Setup once:

1. Push the repository to GitHub.
2. *Settings → Actions → General → Workflow permissions* → **Read and write permissions**.

Every Meteor site checks `JokerM7776/Meteor` (`Core\GitHubUpdates::REPOSITORY`) for updates; a fork
that publishes its own releases changes that constant.

Bump the major/minor part of `VERSION` (e.g. `0.2.0`) to start a new release series.

## Project layout

```
app/        controllers, repositories, services, routes
core/       router, database, i18n, Markdown, auth, WebAuthn, installer, updater
views/      PHP templates (public + admin)
database/   schema.mysql.sql, schema.sqlite.sql, migrations/
bin/        install, seed, migrate, update, package, reset-password, sign-out, login-path, cron, build-css
public/     web root: index.php, assets, uploads
config/     config.php, .env.example
storage/    runtime data (database, logs, backups) — not in git
docs/       TECHNICAL.md: architecture, security model, update internals
tests/      php tests/run.php
```

## License

Meteor is free software, licensed under the
**[GNU Affero General Public License v3.0](LICENSE)** (AGPL-3.0-only).

- You may use, study, change and share Meteor freely, including for your own sites.
- If you distribute Meteor, modified or not, you must provide its source code under the same license.
- If you run a **modified** version that users interact with over a network (for example a
  public blog), you must offer those users the complete source code of your modified version.

Copyright (C) 2026 the Meteor authors. This program comes with ABSOLUTELY NO WARRANTY;
see the license for details.

## Import from Hexo or Typecho

Open **Admin → Import** (`/admin/import`), choose a source and target language, then
**Check** to preview the report without saving content or media. Choose **Import these items** on the report to reuse
the checked files, or **Cancel** to delete them. Checked uploads expire after two hours.
MySQL imports ask for the database password again; it is never saved. Existing slugs are skipped by default; you can choose
new slugs or import everything as drafts. Automatic language selection uses Hexo’s
`_config.yml` language when it matches an existing Meteor locale, otherwise the site default.
Use `--locale=xx` or choose a target language to override that fallback. Language front matter
and recognized language folders still identify translations.

Hexo accepts a zip of the blog folder or its `source/` folder. The CLI also reads folders.
Posts, drafts, pages, dates, categories, tags, translations and local images are imported.
Hexo comments live in third-party services and are not included.

Typecho reads a MySQL/MariaDB database or a SQLite file, including posts, pages, categories,
tags and threaded comments. Supply an optional zip of `usr/uploads` to bring images along;
the CLI also accepts an uploads folder. Use an account with SELECT permission for MySQL.
PostgreSQL is not supported; export to MySQL or SQLite first. The database password is
never saved. Missing images keep their original URLs and appear in the report.

```sh
php bin/import.php hexo /path/to/blog --check
php bin/import.php hexo /path/to/blog.zip --locale=en --drafts
php bin/import.php typecho --driver=sqlite --file=/path/to/typecho.db --uploads=/path/to/uploads.zip --check
php bin/import.php typecho --driver=mysql --host=127.0.0.1 --database=typecho --user=reader --prefix=typecho_ --check
```

The MySQL command prompts for a password without terminal echo when `--password` is omitted.
Remove `--check` to import. Use `--rename` to append a number to conflicting slugs.
Themes and plugins are not imported, and old URLs are not redirected. HTML is converted
to Markdown; unsupported markup and Hexo tags may lose formatting and produce warnings.
Review the imported drafts before publishing.

## Themes

A theme changes the look of the public site: it can replace any public template, add markup in named
slots, ship CSS, images and fonts, and offer settings in the admin. Themes live in
`storage/themes/<name>/`, which updates never touch.

- **Settings → Themes** lists installed themes. **Install the starter theme** adds an example to
  try and copy from; **Preview** shows a theme only to you; **Activate** switches it on for visitors.
- A broken theme template falls back to Meteor's own and is logged in `storage/logs/themes.log`.
- Public pages stay free of JavaScript with any theme.

Writing a theme: [docs/THEMES.md](docs/THEMES.md) (Theme API 1 and the stability promise). The official example,
[meteor-theme-starter](https://github.com/JokerM7776/meteor-theme-starter), is a GitHub template: choose
**Use this template** to start your own.

Theme authoring: **Settings → Themes** installs, updates, creates, copies and downloads themes.
Run `php bin/theme.php check <folder|zip>` before sharing; see [the theme guide](docs/THEMES.md).

**Get themes** in Settings → Themes installs reviewed GitHub release assets and offers updates
that keep settings. Each catalog version is pinned by checksum; install only PHP themes you trust.
See [getting themes](docs/THEMES.md#getting-themes-from-the-catalog) and
[publishing a theme](CONTRIBUTING.md#publishing-a-theme).
