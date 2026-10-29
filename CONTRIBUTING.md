# Contributing to Meteor

Thank you for helping. Bug reports, fixes and translations are all welcome through GitHub issues
and pull requests.

## Translating Meteor

Meteor's interface text (menu labels, buttons, headings such as "Read more" or "Page not found")
lives in one JSON file per language in [`lang/`](lang/). Every Meteor site can download these
files from **Admin → Languages → Get languages**. A merged translation reaches sites right away,
without waiting for a Meteor release.

Translations cover only the words around the content. Posts, pages and site settings are written
by each site owner.

### Add a new language

1. Fork this repository and create a branch.
2. Create the file:
   - With PHP: `php bin/lang.php new ja Japanese 日本語` (add `--rtl` for right-to-left
     languages). This writes `lang/ja.json` with the English text to translate.
   - Without PHP: copy `lang/ms.json` to `lang/<code>.json` and change `code`, `name`,
     `native_name` and `direction`. The English text for every key is in
     [`lang/en.php`](lang/en.php).
   - From a Meteor site: add the language under Admin → Languages, translate it under Settings →
     Interface text, then click *Download file* and rename the file to `lang/<code>.json`.
3. Translate every value. See the rules below.
4. Run `php bin/lang.php check` if you have PHP. The pull request runs the same check.
5. Open a pull request. Say which language and region you wrote for.

### Improve an existing language

Edit `lang/<code>.json` and open a pull request. If you are a native speaker, a short note on why a
change reads better helps the review.

### Rules

- **Language codes** are lowercase [BCP 47](https://www.rfc-editor.org/info/bcp47) tags and become
  part of the site's URLs (`/ja/`, `/pt-br/`). Use the script subtag where it matters:
  `zh-hans` (Simplified Chinese), `zh-hant` (Traditional Chinese), `sr-latn`. Add a region only
  for a real regional variant (`pt-br`, `pt-pt`).
- **Keep placeholders** exactly: `{minutes}`, `{language}`, `{page}` and so on. Each one must stay
  in the text, and you may move it. Never add new ones.
- **Plain text only.** No HTML or Markdown. Meteor escapes everything.
- **Keys** come from `lang/en.php`. Don't add, rename or remove keys. A missing key is fine: the
  site falls back to its default language, then English.
- **`name`** is the language's name in English and **`native_name`** is its name in that language
  (`Japanese` / `日本語`). **`direction`** is `ltr` or `rtl`.
- Match the tone of the English text: short, friendly and plain.
- Don't add author names or credits to the file. Git history records who contributed.

### What happens after merging

`lang/index.json` is the catalog that sites read. It is rebuilt automatically when a pull request
is merged, so you don't need to edit it (`php bin/lang.php index` rebuilds it locally). Sites see
the new or updated language the next time an admin clicks *Check for languages*. New languages
are added switched Off, and a site's own text edits are always kept.

## Code changes

[`docs/TECHNICAL.md`](docs/TECHNICAL.md) explains the architecture. The rules:

- No frameworks, no Composer and no npm. Standard PHP extensions only.
- Public pages ship no `<script>` tags; the admin may load `/assets/admin.js` only.
- Escape every dynamic value with `e()`, and use prepared statements for every query with input.
- Every POST form and admin JSON request carries the CSRF token.
- SQL must work on MariaDB/MySQL and SQLite; update both files in `database/`.
- Code that runs right after an online update (migrations, language sync) may only call methods
  the previous release already had. `tests/UpgradeTest.php` checks this.

Before opening a pull request, run:

```sh
php tests/run.php
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

## Publishing a theme

Keep theme code in your own GitHub repository. Meteor holds only the reviewed listing in
`themes/index.json`; each version pins the exact release zip by SHA-256. The easiest start is the
official example, [meteor-theme-starter](https://github.com/JokerM7776/meteor-theme-starter): choose
**Use this template**, and see how its release and catalog entry are set up.

1. Include `slug`, `version` (`X.Y.Z`), `api`, `requires` (`X.Y` or `X.Y.Z`) and `license`
   in `theme.json`, along with its name, description and author. See [the Theme API guide](docs/THEMES.md).
2. Run `php bin/theme.php check <folder>` and fix every finding: the result must be zero findings.
3. Build a zip with `theme.json` at the top or inside one folder. Include no dot files.
4. Attach the zip to a GitHub release in your repository, using the version as its tag.
5. Generate the entry:
   ```sh
   php bin/theme.php entry owner/repo https://github.com/owner/repo/releases/download/1.0.0/my-theme.zip
   ```
   An optional final `local.zip` argument computes the entry from that local file without downloading.
   Upload those exact bytes as the release asset. Paste the resulting slug and entry into the index's
   `themes` object.
6. Open a PR adding or updating **only `themes/index.json`**. Run `php bin/theme.php catalog check`
   to validate it. CI runs `php bin/theme.php catalog check --download`, verifying the pinned bytes,
   matching manifest and theme checks. Maintainers can limit downloads with `--download slug ...`.

Reviewers check escaping with `e()`, use of only the documented Theme API, a present license,
no network calls, no file writes, and no direct `$_GET`, `$_POST` or `$_SERVER` access.
Themes execute PHP; passing the checker is not a security guarantee. The download check executes
untrusted theme PHP in the PR runner with read-only permissions and no secrets.

For an update, publish a new release and submit a new PR with the new version and SHA-256.
Do not replace an approved asset's bytes. Maintainers may delist a theme; sites keep their installed
copy, but no longer see it in subsequent catalog checks.
