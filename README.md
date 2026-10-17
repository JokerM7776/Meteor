# Meteor

A fast, multilingual blog in plain PHP. Public pages are server-rendered HTML and CSS with
**zero JavaScript**; a small admin area handles writing, translating and site settings.

- Write once, translate into any number of languages, each with its own URL (`/en/…`, `/zh/…`).
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
| PHP | **8.2 or newer** with `pdo`, `pdo_mysql` and/or `pdo_sqlite`, `openssl`, `mbstring`, `intl`, `fileinfo`, `zip`, `curl` |
| Database | **MariaDB 10.5+ / MySQL 8.0.16+** (recommended) or **SQLite 3** |
| Web server | Apache with `mod_rewrite`, or Nginx + PHP-FPM |
| HTTPS | Required for passkeys on a real domain (`localhost` works without it) |

Check PHP with `php -v` and `php -m`.

## 2. Install from a release (recommended)

1. Download `meteor-<version>.zip` (and its `.sha256`) from the
   [Releases](../../releases) page. Optionally verify it:
   `shasum -a 256 -c meteor-<version>.zip.sha256`
2. Unzip it on your server. You get one `meteor/` folder.
3. Point your web server's **document root at `meteor/public`** — never at `meteor/` itself
   (see [Web server configuration](#5-web-server-configuration)).
4. If you use MariaDB/MySQL, [create an empty database and user](#3-create-the-database).
5. Open your site in a browser. You are taken to **`/install`**, which:
   - checks PHP requirements and folder permissions,
   - asks for the database (MariaDB/MySQL or SQLite) with a **Test connection** button,
   - asks for the site URL and title,
   - asks you to **choose the admin username and password** (12+ characters).
6. Sign in at **`/admin/login`**. The installer locks itself afterwards (`/install` returns 404).

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
| `WEBAUTHN_RP_ID` | `blog.example.com` | Passkey domain: the hostname of `APP_URL`, no scheme or port. |
| `WEBAUTHN_RP_NAME` | `Meteor` | Name shown in the passkey prompt. |

Environment variables set by the server override `.env`. **Never commit `.env`** — it is in
`.gitignore`, and only the template `config/.env.example` belongs in git.

## 5. Web server configuration

**Apache** — `public/.htaccess` is included (needs `AllowOverride All` and `mod_rewrite`):

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
for online updates — the application files. For cover images allow `upload_max_filesize = 8M`;
for uploading update zips allow `upload_max_filesize = 50M` and `post_max_size = 55M`.

## 6. Using Meteor

- **Admin:** `/admin` — Posts (translation matrix), Pages, Categories & tags, **Languages**,
  Settings, **Security**, **Updates**.
- **Add a language:** Admin → Languages → *Add a language*. It starts in **Preview**; translate,
  then click **Go live**. English is the default language.
- **Passkeys:** Admin → Security → *Add a passkey*. Requires HTTPS (or `localhost`) and correct
  `APP_URL` / `WEBAUTHN_RP_ID`. Password sign-in always stays available as a fallback.
- **Change username or password:** Admin → Security. Changing the password signs out other sessions.
- Failed sign-ins are limited to 5 per 15 minutes per IP and username.

## 7. Updating

- **Online:** Admin → Updates → enter the GitHub repository as `owner/name` → *Check for updates*
  → *Download and install*. The checksum is verified and your password is required.
- **Manual:** Admin → Updates → *Upload update zip*, or from the shell:
  `php bin/update.php /path/to/meteor-<version>.zip`

Every update backs up the current code first (the last 5 backups can be restored from the same
page), shows visitors a short maintenance page, runs database migrations, and **never touches
`.env`, `storage/` or `public/uploads/`**. Code restores do not undo database changes, so
**back up your database before updating**.

## 8. Reset an admin password

From the server shell, in the project folder:

```sh
php bin/reset-password.php <username>
```

It asks for the new password (12+ characters), signs out other sessions and clears the
failed-login counter. Use `NEW_PASSWORD='…' php bin/reset-password.php <username>` in scripts.

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
| Lint PHP | `find . -name '*.php' -not -path './design/*' -print0 \| xargs -0 -n1 php -l` |
| Rebuild CSS | `bin/build-css.sh` (needs the [Tailwind v4 standalone CLI](https://tailwindcss.com/blog/standalone-cli), e.g. `brew install tailwindcss`) |
| Run migrations | `php bin/migrate.php` |
| Build a release zip | `php bin/package.php` → `dist/meteor-<version>.zip` |

The compiled `public/assets/style.css` is committed, so servers never need Tailwind.

## 10. Releases (GitHub Actions)

`.github/workflows/release.yml` runs on every push to `main`: it lints, runs the tests,
rebuilds the CSS, packages `meteor-<major.minor>.<run number>.zip` with its `.sha256`, and
publishes a GitHub Release tagged `v<version>`. Setup once:

1. Push the repository to GitHub.
2. *Settings → Actions → General → Workflow permissions* → **Read and write permissions**.
3. In your Meteor admin, *Updates* → set the repository to `owner/name`
   (a private repository also needs a fine-grained token with read access to contents).

Bump the major/minor part of `VERSION` (e.g. `0.2.0`) to start a new release series.

## Project layout

```
app/        controllers, repositories, services, routes
core/       router, database, i18n, Markdown, auth, WebAuthn, installer, updater
views/      PHP templates (public + admin)
database/   schema.mysql.sql, schema.sqlite.sql, migrations/
bin/        install, seed, migrate, update, package, reset-password, build-css
public/     web root: index.php, assets, uploads
config/     config.php, .env.example
storage/    runtime data (database, logs, backups) — not in git
tests/      php tests/run.php
design/     design mockups (reference only)
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
