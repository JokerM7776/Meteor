# Meteor — agent brief

Meteor is a multilingual blog: **vanilla PHP 8.x with zero frameworks and zero Composer packages**,
a **zero-JavaScript public frontend**, and a small admin area where minimal JS is allowed.

- Original spec: `draft/multilingual_zero_js_blog_architecture.md`. Read it first.
- Visual design: `design/*.dc.html`. These are design-canvas mockups with inline styles. The
  `<x-dc>`, `<helmet>`, `support.js` and `data-dc-script` parts are canvas wrappers; ignore them and
  copy the markup structure, copy text, spacing, colors and type into real templates using
  **Tailwind classes**. `design/Foundations.dc.html` maps every token to a Tailwind class.
- The decisions below **override the spec** where they differ.

## Decisions (confirmed by the owner)

1. **Languages are configurable, not hard-coded.** English (`en`) is the default and the only
   language seeded by the installer. Languages live in a `locales` table with a status:
   - `live`: public; in the switcher, sitemap, hreflang tags and its own RSS feed.
   - `preview`: only signed-in admins can open its URLs; everyone else gets a 404.
   - `off`: hidden everywhere; translations are kept.
   The router builds its valid prefixes from this table. Order of the switcher = `sort_order`.
   UI strings ("Read more" etc.) live in a `ui_strings` table (locale, key, value). `lang/en.php`
   holds the built-in English defaults; a missing key falls back to the default locale, then to
   `lang/en.php`, then to the key itself. Never assume `zh`/`ms` exist — they are only examples.
2. **Database: MariaDB/MySQL is the primary target, SQLite must also work.** Use PDO only. Keep SQL
   to the portable subset. Ship `database/schema.mysql.sql` and `database/schema.sqlite.sql`
   (utf8mb4 / InnoDB on MySQL). No `ENUM` (use `VARCHAR` + `CHECK`); keep driver-specific SQL
   (upserts, auto-increment, date functions) behind small methods in `core/Database.php`.
   Driver is chosen in `.env` (`DB_DRIVER=mysql|sqlite`).
3. **Auth: passkeys (WebAuthn) first, password as a permanent fallback.** Implement WebAuthn in
   pure PHP: a minimal CBOR decoder, authenticatorData parsing, COSE → PEM for ES256 (-7) and
   RS256 (-257), `openssl_verify`. Attestation `none` only. Check challenge, origin, rpIdHash,
   UP flag, and sign counter. The login page uses `autocomplete="username webauthn"` and a
   "Sign in with a passkey" button (small admin JS file, no libraries).
4. **Admin pages** (see design): Login, Posts (translation matrix), Editor (side-by-side), Pages,
   Categories & tags, **Languages**, Settings (site title / nav labels / UI strings per locale,
   custom CSS), **Security** (change username, change password, list/add/rename/remove passkeys).
5. **Styling: Tailwind CSS v4** via the standalone CLI (`tailwindcss`, already installed — no Node).
   Input `src/app.css`, output `public/assets/style.css` (minified, **committed** so deploys need no
   build step). Dark mode = Tailwind's default `dark:` variant (`prefers-color-scheme`).
   Fonts (Google Fonts `<link>`, OFL): display/UI **Source Sans 3**, article body
   **Source Serif 4**, mono **JetBrains Mono**, with **Noto Sans SC** / **Noto Serif SC** as CJK
   fallbacks. Theme in `src/app.css`:
   ```css
   @import "tailwindcss";
   @source "../views";
   @theme {
     --font-display: "Source Sans 3", "Noto Sans SC", system-ui, sans-serif;
     --font-serif: "Source Serif 4", "Noto Serif SC", Georgia, serif;
     --font-mono: "JetBrains Mono", ui-monospace, monospace;
     --color-meteor: var(--color-orange-500);
   }
   ```
   Palette: slate for ink/ground, orange for the meteor accent (`text-orange-700` links,
   `dark:text-orange-400`). Translation status chips: published = solid slate-900, draft = amber-100,
   missing = dashed outline (they differ by shape and lightness, not hue alone).

## Hard rules

- No frameworks, no Composer, no npm. PHP standard extensions only (pdo, pdo_mysql, pdo_sqlite,
  openssl, mbstring, intl allowed).
- **Public pages ship zero `<script>` tags.** Menus use `<details>`/`<summary>`. Send a CSP header
  on public pages with `script-src 'none'`. Admin pages may load `/assets/admin.js` only.
- Escape every dynamic value with the `e()` helper (`htmlspecialchars`, ENT_QUOTES, UTF-8).
  Markdown never passes raw HTML through.
- Prepared statements for every query with input. No string-built SQL from user data.
- CSRF token on every POST form and admin JSON POST. `session_regenerate_id(true)` on login.
  Session cookie: HttpOnly, SameSite=Lax, Secure when HTTPS. Rate-limit failed logins.
- Markdown → HTML and TOC are compiled **on save** into `content_html` / `toc_html`; public pages
  only echo stored HTML. Heading ids must support CJK text.
- Accessibility as in the design: real `<a>`, `<button>`, `<label>`; touch targets ≥ 44px;
  `aria-current` on the active language/nav item; `lang` attributes on language names.
- Keep files small and readable. Comment only non-obvious logic.

## Layout

```
/config        config.php (reads .env), .env.example
/core          Router, Database, Markdown, I18n, View, Csrf, Session, Auth, WebAuthn, Cbor, helpers
/app           controllers (Public/*, Admin/*) and small repositories/services
/views         public/ and admin/ PHP templates + layouts/partials
/lang          en.php (built-in English UI defaults)
/database      schema.mysql.sql, schema.sqlite.sql
/bin           install.php (create schema + first admin + en locale), seed.php (demo content),
               build-css.sh
/public        index.php (single entry), .htaccess, assets/, uploads/
/src           app.css (Tailwind input)
/storage       sqlite file, logs (git-ignored)
/tests         run.php — a tiny assertion runner (no PHPUnit); one file per area
```

## Commands

- Run tests: `php tests/run.php` (must exit 0).
- Lint: `find . -name '*.php' -not -path './design/*' -print0 | xargs -0 -n1 php -l`
- Build CSS: `bin/build-css.sh` (runs `tailwindcss -i src/app.css -o public/assets/style.css --minify`)
- Dev server: `php -S localhost:8000 -t public public/index.php`
- SQLite install for dev/tests: `DB_DRIVER=sqlite php bin/install.php`

## Working agreement

- Work only inside this repository. Do not commit; the supervisor reviews `git diff` and commits.
- No network access is needed or available. Do not download anything.
- MariaDB is not installed on this machine: test against SQLite, and keep MySQL SQL correct by
  construction (and covered by a test that at least parses/loads `schema.mysql.sql` statements).
- End every task with a short summary: what was built, files touched, test results, known gaps.
