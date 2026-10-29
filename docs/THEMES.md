# Meteor themes

A theme changes how the public site looks: it can replace any public template, add markup in named
slots, ship its own CSS, images and fonts, and offer settings that site owners change in the admin.
The admin area is never themed.

This guide is for **Theme API 1** (Meteor 0.5 and newer).

## Quick start

1. In the admin, open **Settings → Themes** and choose **Install the starter theme**. This copies
   Meteor's example theme to `storage/themes/starter/`.
2. Choose **Preview**. You see the site with the theme; visitors still see the current look. A banner
   at the top links back to the admin to stop the preview.
3. Choose **Activate** to switch it on for everyone. Visitors may see the previous look for up to
   5 minutes (the public cache).
4. Start your own theme from it: choose **Use this template** on
   [meteor-theme-starter](https://github.com/JokerM7776/meteor-theme-starter), or copy
   `storage/themes/starter/` to `storage/themes/<your-theme>/`. Change `slug` and `name` in its
   `theme.json`, and start editing.

Themes live in `storage/themes/`, which updates never touch.

## Installing and updating

Open **Settings → Themes**, choose a zip (up to 20 MB), and upload it. Themes run PHP code on
your server; install only themes from people you trust. Uploading requires PHP's zip extension.
A zip has `theme.json` at its root or inside one enclosing folder, with a required `slug`.
Meteor checks the manifest, file paths, file types, language text and PHP syntax before installation.
Limits: 1000 entries, 50 MB expanded, 5 MB per file. JavaScript and symlinks are rejected;
dot-files, dot-folders and macOS metadata are skipped.

To update, upload a new version and tick **Replace the installed theme with the same name**.
The folder is replaced; settings stored under the same slug survive. Keep your own edits in a
separate theme so an update does not replace them. Preview and activate work as before.

**Delete** opens a confirmation page. It permanently removes the folder and its theme settings,
and stops its preview. Switch away from an active theme before deleting it. The built-in look
cannot be deleted. Site interface-text edits are kept separately.

## Getting themes from the catalog

In **Settings → Themes → Get themes**, choose **Check for themes**, then **Install**.
The catalog is a reviewed index on GitHub; theme zips stay in their authors' repositories.
Every version is pinned by SHA-256 and size. Themes run PHP on your server, so install only
code you trust. Preview the installed theme, or activate it when ready.

**Update available** appears in both tables when a catalog theme has a newer version. Choose
**Update** to replace its files while keeping settings. Older catalog versions never downgrade
an installed theme. **Name in use** means a local folder already has that slug; the catalog
will not overwrite it. Incompatible themes show **Needs Meteor** and have no install button.

Meteor records catalog provenance (version, checksum, repository and installation time) in
`storage/themes/.installed.json`; the last check lives in `.catalog.json`. No index is fetched
implicitly during installation. **From the catalog** marks installed catalog themes, even if
later delisted. A hand upload with **Replace** clears provenance and makes the theme local;
deleting a theme also clears its record. Keep file edits in a separate local theme, since a
catalog update replaces theme files.

## Publishing

See [Publishing a theme](../CONTRIBUTING.md#publishing-a-theme) for the own-repository release
workflow, `php bin/theme.php entry`, catalog PRs and review requirements.

## Creating and sharing

**Create a theme** takes a name and an optional ASCII folder name. Meteor derives a folder name
when possible (otherwise it uses `theme-<random>`), then writes a manifest and commented `theme.css`.
It never overwrites an existing folder. The command-line equivalent is:

```sh
php bin/theme.php new my-theme "My Theme"
```

**Copy a template** lists every built-in public layout, page and partial with a description.
It copies the selected file unchanged into your theme and tells you where to edit it. Existing
files are never overwritten. Start with a slot or one partial rather than copying every template.
Each theme row lists its overrides, slots, stylesheet and interface text.

**Download** saves `<slug>-<version>.zip`, with the slug as its top folder. Hidden files and
symlinks are omitted. Share the archive with people who trust your code.

## Interface text

Add `lang/en.json`, `lang/zh-hans.json`, or another language code to your theme. Each is a JSON
object of key → text, at most 64 KB. Keys match `[a-z][a-z0-9_]{0,80}` and text is at most
1000 characters. Old `zh` codes map to `zh-hans`. Invalid files make the theme unavailable.

```json
{
  "starter_more_posts": "Follow more posts",
  "latest": "Recent writing"
}
```

Prefix your own keys with your slug (use underscores in keys, for example `my_theme_subscribe`).
Call `t('starter_more_posts')` and escape it with `e()` like any other text; HTML is never interpreted.
You may reword a built-in key, but must keep exactly its built-in placeholders, such as `{minutes}`.

Precedence: site edits → the rendered theme's text → the language file; then those same three
for the default language → the theme's English text → built-in English → the key. Only the active
or previewed theme applies, and admin pages never use theme translations.

**Settings → Interface text** adds a **From <theme name>** group for the active theme's own keys.
Its text is the base/placeholder; cells save site edits. Only keys belonging to the current active
theme can be saved. Language export/import and missing-text counts remain built-in-only.

## Checking a theme

```sh
php bin/theme.php check storage/themes/my-theme
php bin/theme.php check my-theme.zip
```

The checker works in release archives without `tests/`. It creates a disposable SQLite site under
`storage/`, seeds demo articles, languages and comments, installs the theme, and renders home pages,
archives (including page 2), category/tag lists, a post with comments, a page, search results and
empty search, 404 and comment verification. It cleans up afterward and never opens the real database.
It executes PHP from the theme: run it only on code you trust, just as with installation.

Findings include manifest/file/syntax problems, template exceptions and fallbacks (file and line),
deprecated field reads, script elements, inline styles, style elements, event handlers and missing
theme assets. Findings go to the report instead of the site theme log. Exit status is 0 for no
findings and 1 otherwise. This is a fixture-based contract check, not a security sandbox or a proof
that every possible content/settings combination works.

On the comment verification page, the CAPTCHA provider's script (`$page->commentCheck->script`) is
Meteor's own and allowed there; the checker accepts exactly that script. A theme that overrides
`public/comment-check.php` must keep it, or comments cannot be sent while CAPTCHA is on. The checker
reports a missing CAPTCHA script.

## The stability promise

Theme API 1 is a contract between Meteor and theme authors:

- New fields, objects, helpers and slots may appear in any Meteor release.
- Nothing documented here is renamed or removed while API 1 is current. That only happens in API 2,
  and then the API 1 names keep working for at least one more major version, with one line per name
  in `storage/logs/themes.log` telling you what to use instead.
- A theme declares the API it was built for (`"api": 1`). Meteor refuses to activate a theme built
  for an API it does not support.

Only what this guide documents is covered. Meteor's classes, database tables, settings keys and the
built-in templates' markup and CSS classes are **not** part of the promise and may change.

## Folder layout

Every file is optional except `theme.json`. A theme only contains the files it changes; everything else
comes from Meteor.

```
storage/themes/my-theme/
  theme.json                 required: name, slug, api, settings
  theme.css                  linked automatically after Meteor's CSS
  layouts/public.php         the page frame (<html> … </html>)
  public/home.php            page templates (see "Templates")
  partials/author-card.php   partials (see "Partials")
  slots/post_after.php       slots (see "Slots")
  img/, fonts/ …             assets, served from /themes/my-theme/…
```

The folder name is the theme's slug: lowercase letters, numbers and hyphens, starting with a letter or
number, up to 63 characters.

## theme.json

```json
{
  "name": "My Theme",
  "slug": "my-theme",
  "version": "1.0.0",
  "api": 1,
  "requires": "0.5.0",
  "author": "Your name",
  "homepage": "https://example.com/my-theme",
  "license": "MIT",
  "description": "One sentence shown in the admin.",
  "settings": [
    { "key": "sidebar", "type": "toggle", "label": { "en": "Show sidebar", "zh-hans": "显示侧栏" }, "default": true },
    { "key": "hero", "type": "select", "label": "Hero style", "options": { "plain": "Plain", "photo": "Photo" }, "default": "plain" },
    { "key": "tagline", "type": "text", "label": "Hero text", "max": 200, "default": "" },
    { "key": "accent", "type": "color", "label": "Accent", "default": "#ea580c" }
  ]
}
```

| Key | Required | Meaning |
|---|---|---|
| `name` | yes | Shown in the admin (up to 100 characters). |
| `slug` | uploads: yes | Must match the folder name when present. |
| `api` | yes | The Theme API the theme was built for. Today: `1`. |
| `version` | no | Your theme's version, shown in the admin and available as `$theme->version`. |
| `requires` | no | Oldest Meteor version the theme supports, such as `"0.5.0"`. |
| `author`, `homepage`, `license`, `description` | no | Shown in the admin. `homepage` must be an http(s) URL. |
| `settings` | no | Options the site owner can change, see below. |

Unknown keys are ignored, so newer themes still load in older Meteor releases that share their API.
A theme with a problem (bad JSON, wrong slug, unsupported API, a Meteor version that is too old, an
invalid setting) is listed in the admin with the reason and cannot be activated or previewed.

### Settings

Meteor builds the settings form in **Settings → Themes** for the active theme and stores the values.
Read them in templates with `$theme->setting('key')`.

| Type | Value | Notes |
|---|---|---|
| `toggle` | `true` / `false` | A checkbox. Default `false`. |
| `select` | string (an option key) | `options`: `{ "value": "Label" }`. Default: the first option. |
| `text` | string | `max`: 1 to 1000 characters (default 200). Longer than 200 shows a text area. |
| `color` | `#rrggbb` | Also available as the CSS variable `--theme-<key>` (see "CSS"). Default `#000000`. |

`key` uses lowercase letters, numbers and `_` (up to 41 characters) and must be unique. A `label`, and
each option label, is either text or one text per language: `{ "en": "…", "zh-hans": "…" }`. The admin
shows the label in the site's default language, then English, then the first one given.

If a stored value stops being valid (for example after you remove an option), the default is used.

## Templates and objects

Every public template, partial and slot receives the same three variables:

| Variable | What it is |
|---|---|
| `$site` | The site: title, languages, menus, author, appearance… ([Site](#site)) |
| `$page` | What is being shown: its type, title, post, posts, comments… ([Page](#page)) |
| `$theme` | The active theme and its settings ([Theme](#theme)) |

The layout also receives `$content`: the page template's HTML. Partials also receive the `$data` they
were called with. Nothing else is in scope; variables set in one template never leak into another.

All values are read-only objects. Lists are PHP arrays. Dates are [Date](#date) objects already
formatted for the page's language.

### Which template renders which page

| `$page->type` | Template | Notes |
|---|---|---|
| `home` | `public/home.php` | `$page->home`, `$page->pinned`, `$page->posts`, `$page->categories` |
| `post` | `public/item.php` | `$page->post`, `$page->comments`, `$page->neighbors` |
| `page` | `public/item.php` | `$page->post` with `isPage` true |
| `archive` | `public/archive.php` | All posts, or one category or tag (`$page->term`). `$page->archive`, `$page->pagination` |
| `tags` | `public/archive.php` | The tags overview |
| `search` | `public/search.php` | `$page->query`, `$page->results` |
| `not_found` | `public/not-found.php` | `$page->requestedPath` |
| `comment_check` | `public/comment-check.php` | `$page->commentCheck`. The only public page that may load a script (the CAPTCHA). |

All of them render inside `layouts/public.php`.

## Helpers

| Helper | Returns |
|---|---|
| `e($value)` | The value escaped for HTML. Use it for every value you print. |
| `t('key', ['name' => 'value'])` | Interface text in the page's language (the keys of `lang/en.php`, such as `read`, `latest`, `search`). |
| `partial('name', ['key' => $value])` | A partial's HTML, from the theme if it has one, else Meteor's. |
| `slot('name')` | A slot's HTML from the theme, or an empty string. |
| `asset('assets/public.css')` | Versioned URL of one of Meteor's own files. |
| `url($path)` | A root-relative URL for a path. |
| `absolute_url($path)` | The full URL (with `https://` and the domain) for a root-relative path. |
| `language_names($languages)` | Escaped HTML list of language names ("English, 简体中文 and Bahasa Melayu"), each with its `lang`. |

Print `->html`, `->toc` and `CommentCheck->html` without `e()`: Meteor compiled them from Markdown on save
and they are already safe. Everything else goes through `e()`.

## Partials

A theme can override any of these by adding the same file under `partials/`. Call them with `partial()`.

| Partial | `$data` it receives | Used in |
|---|---|---|
| `announcements` | — | Layout, when `$site->announcements` is not empty |
| `author-card` | `author` (Author), `variant` (`home`, `post` or `footer`) | Home, posts, footer |
| `comments` | — (reads `$page->comments`) | Posts |
| `cover` | `post` (Post) | Home cards, posts |
| `favicon` | — | Layout `<head>` |
| `fonts` | — | Layout `<head>` |
| `mark` | — | Layout: the site icon or Meteor's mark |
| `nav` | `stacked` (bool, the phone menu) | Layout |
| `new-tab` | `icon` (bool) | After the label of a link that opens in a new tab |
| `read-link` | `post` (Post), `button` (bool) | Home lists |

A theme may also add partials of its own (`partials/my-card.php`) and call them with `partial('my-card')`.

## Slots

Slots let a theme add markup without replacing a whole template, so it keeps working when Meteor's
templates change. Add `slots/<name>.php`; without it, a slot prints nothing.

| Slot | Where it renders |
|---|---|
| `head` | End of `<head>` |
| `body_start` | Start of `<body>`, before the skip link |
| `header_after` | After the site header |
| `main_before` | Start of `<main>` |
| `main_after` | End of `<main>` |
| `home_before` | Home page, before the first section |
| `home_after` | Home page, after the last section |
| `post_before` | Post or page, before the text |
| `post_after` | Post or page, after the text (before tags, author card and comments) |
| `archive_before` | Archive pages, before the heading |
| `archive_after` | Archive pages, after the page links |
| `footer_before` | Before the footer |
| `footer` | Inside the footer, above the copyright line |
| `body_end` | End of `<body>` |

A slot sees `$site`, `$page` and `$theme`, so it can decide when to show something
(`if ($page->type === 'post')`). A theme that replaces a template keeps the slots it wants by calling
`slot()` itself.

## CSS

- `theme.css` in the theme folder is linked after Meteor's stylesheet and before the site's custom CSS,
  so site owners can still adjust a theme.
- Meteor's own stylesheet contains only the Tailwind classes its built-in templates use. A class your
  templates add may not exist there. Either write plain CSS in `theme.css` (the starter theme does), or
  build `theme.css` with the [Tailwind CLI](https://tailwindcss.com/docs/installation/tailwind-cli):
  ```css
  /* theme.src.css in your theme folder */
  @import "tailwindcss";
  @source "./";
  ```
  `tailwindcss -i theme.src.css -o theme.css --minify`
- Color settings become CSS variables in a small stylesheet Meteor links for you:
  `var(--theme-accent)` for a setting with key `accent` (`_` becomes `-`). Public pages do not allow inline
  `style` attributes or `<style>` elements, so use classes and these variables.
- Dark mode: Meteor sets `data-theme="light"`, `"dark"` or `"auto"` on `<html>`. With `auto`, follow
  `prefers-color-scheme`. The starter theme's `theme.css` shows both.

## Assets

Files in the theme folder with these extensions are served at `/themes/<slug>/<path>`:
css, png, jpg, jpeg, gif, webp, avif, svg, ico, woff, woff2 (up to 5 MB each). PHP, JSON, HTML,
JavaScript and files or folders starting with a dot are never served. Link them with
`$theme->asset('img/logo.svg')`, which adds a version so browsers can cache them for a year.

Only the active theme's files are public. A signed-in admin can also load the files of a theme they
are previewing.

## No JavaScript

Public pages send `Content-Security-Policy: script-src 'none'`. A theme cannot add scripts, inline
event handlers or inline styles; browsers ignore them. Use HTML and CSS: `<details>`, `:target`,
`:checked` and `@media` cover menus, tabs, toggles and dark mode. Accessibility matters as much as in
Meteor's own templates: real links and buttons, labels, at least 44 px touch targets, `lang` on
language names and `aria-current` on the current navigation item.

## When a template fails

If a theme template throws an error (including a PHP syntax error), Meteor discards its output, renders
its own template instead and writes one line to `storage/logs/themes.log`:

```
2026-10-28 09:30:00 Theme my-theme template public/home: Call to undefined function x() …/home.php:12
```

A broken theme never takes the site down. Check the log while you work.

## Preview and caching

**Preview** in the admin shows a theme only to you, with a banner (Meteor's, not the theme's) to stop
the preview. Previews are never cached. After **Activate**, visitors may see the previous look for up to
5 minutes. Settings changes behave the same way.

## API reference

Types: `?Image` means an Image or `null`. `list<Post>` is an array of Post objects. Properties listed
as language-dependent are already in the page's language.

### Site

| Property | Type | Meaning |
|---|---|---|
| `title` | string | Localized site title. |
| `tagline` | string | Localized tagline. |
| `url` | string | Absolute site base URL. |
| `icon` | ?Image | Site icon chosen in Settings; null when none is set. |
| `favicon` | Image | Browser icon: the site icon, or Meteor's default icon. |
| `locale` | Language | Current language. |
| `languages` | array<string,Language> | Switcher languages keyed by code. |
| `liveLanguages` | array<string,Language> | Public (live) languages keyed by code; their url is their home page. |
| `defaultLanguage` | Language | Default language. |
| `header` | Header | Header configuration. |
| `footer` | Footer | Footer configuration. |
| `author` | ?Author | Author profile. |
| `postAuthorCard` | bool | Whether posts display the author card. |
| `announcements` | list<Announcement> | Visible announcements. |
| `appearance` | Appearance | Color appearance configuration. |
| `fonts` | Fonts | Resolved font resources. |
| `feedUrl` | string | Current language feed URL; empty in preview locales. |
| `searchUrl` | string | Search URL. |
| `homeUrl` | string | Home URL. |
| `postsUrl` | string | Post archive URL. |

### Page

| Property | Type | Meaning |
|---|---|---|
| `type` | string | home, post, page, archive, tags, search, not_found or comment_check. |
| `title` | string | Page title. |
| `description` | string | Meta description. |
| `canonical` | ?string | Absolute canonical URL. |
| `alternates` | array<string,string> | Absolute URLs keyed by hreflang. |
| `noindex` | bool | Prevent search indexing. |
| `shareImage` | ?Image | Image for link previews (og:image); null when there is none. |
| `language` | Language | Language of this page. |
| `post` | ?Post | Post or page being shown; null on other pages. |
| `posts` | list<Post> | Home: the latest posts (pinned posts are in pinned). |
| `pinned` | list<Post> | Home: pinned posts, when the Pinned section is on. |
| `pagination` | ?Pagination | Archive pages: page links; null elsewhere. |
| `term` | ?Term | Category or tag archive: the chosen term; null elsewhere. |
| `categories` | list<Term> | Home and archive: categories with post counts. |
| `tags` | list<Term> | Archive: every tag with its post count (a post's own tags are in post->tags). |
| `archive` | list<ArchiveYear> | Archive posts on this page, grouped by year and month (site time zone). |
| `query` | string | Search query. |
| `queryMaxLength` | int | Longest accepted search query, in characters. |
| `results` | list<SearchResult> | Search results. |
| `comments` | ?Comments | Post: comments, mentions and the form; null when comments are off and there are none. |
| `neighbors` | array<string,Post\|null> | Post: "prev" (older) and "next" (newer) post in this language. |
| `home` | ?Home | Home: the sections and intro from Settings → Home page; null elsewhere. |
| `toc` | string | Post or page: table of contents HTML (already safe); empty when there is none. |
| `requestedPath` | string | not_found: the path that was asked for. |
| `active` | string | Navigation key of this page: posts, tags, search or a page slug. |
| `suffix` | string | Path after the language prefix, such as /posts/hello. |
| `webmention` | string | Post: Webmention endpoint URL, or empty. |
| `commentCheck` | ?CommentCheck | comment_check: the CAPTCHA step of a comment; null elsewhere. |

### Theme

The active theme (or the built-in look, whose `slug` is empty).

| Property | Type | Meaning |
|---|---|---|
| `slug` | string | Folder name; empty for the built-in look. |
| `name` | string | Display name from theme.json. |
| `version` | string | Version from theme.json; empty for the built-in look. |

| Method | Returns |
|---|---|
| `setting($key)` | A setting's value (bool for toggle, string otherwise), or null when the theme declares no such setting. |
| `asset($path)` | Versioned URL of a file in the theme folder (css, images, fonts). Throws for a missing or disallowed file. |
| `hasAsset($path)` | Whether the theme folder has this asset (always false for the built-in look). |
| `settingsCss()` | The color settings as CSS variables: ":root{--theme-accent:#ea580c}". Empty without color settings. |
| `settingsCssUrl()` | URL of settingsCss() as a stylesheet (public pages allow no inline styles), or '' when there is none. |

### Post

| Property | Type | Meaning |
|---|---|---|
| `id` | int | Content identifier. |
| `isPage` | bool | True for a static page. |
| `slug` | string | Content slug. |
| `url` | string | Localized URL. |
| `title` | string | Localized title. |
| `summary` | string | Plain text summary. |
| `html` | string | Safe compiled article HTML. |
| `toc` | string | Safe compiled table of contents HTML. |
| `readingMinutes` | int | Estimated reading time. |
| `published` | ?Date | Publication date; null for pages. |
| `updated` | ?Date | Last updated date when available. |
| `cover` | ?Image | Cover image. |
| `category` | ?Term | Category when available. |
| `tags` | list<Term> | Tags when available. |
| `translations` | list<Language> | Published translations. |
| `pinned` | bool | Pinned on the home page. |
| `commentsEnabled` | bool | Comments enabled for this post. |
| `commentCount` | ?int | Comment count when available. |
| `language` | Language | Content language. |

### Term

| Property | Type | Meaning |
|---|---|---|
| `slug` | string | Term slug. |
| `name` | string | Localized term name. |
| `url` | string | Archive URL. |
| `count` | int | Published post count. |
| `description` | string | Localized description, empty when unavailable. |

### Language

| Property | Type | Meaning |
|---|---|---|
| `code` | string | Locale code. |
| `name` | string | Language name. |
| `nativeName` | string | Native language name. |
| `dir` | string | ltr or rtl. |
| `url` | string | URL for this language on the current page. |
| `current` | bool | Whether this is the current language. |
| `preview` | bool | Whether only admins can see this language. |
| `shortLabel` | string | Compact switcher label. |
| `homeUrl` | string | Home page URL in this language. |
| `feedUrl` | string | RSS feed URL in this language. |

### Link

| Property | Type | Meaning |
|---|---|---|
| `label` | string | Link label. |
| `url` | string | Destination URL. |
| `current` | bool | Current navigation item. |
| `external` | bool | External URL. |
| `newTab` | bool | Open in a new tab. |
| `email` | bool | Email link. |

### Image

| Property | Type | Meaning |
|---|---|---|
| `url` | string | Image URL. |
| `width` | int | Pixel width, zero when unknown. |
| `height` | int | Pixel height, zero when unknown. |
| `alt` | string | Alternative text. |
| `thumbUrl` | ?string | Thumbnail URL when known. |
| `type` | string | MIME type, empty when unknown. |

### Date

| Property | Type | Meaning |
|---|---|---|
| `iso` | string | Stored UTC timestamp, suitable for datetime attributes. |
| `utc` | DateTimeImmutable | UTC date and time. |
| `long` | string | Site-format localized date. |
| `short` | string | Localized two-digit day. |
| `month` | string | Localized month name. |
| `time` | string | Localized time. |

### Author

| Property | Type | Meaning |
|---|---|---|
| `name` | string | Display name. |
| `bio` | string | Localized biography. |
| `avatar` | ?Image | Profile image. |
| `links` | list<Link> | Profile links. |

### Announcement

| Property | Type | Meaning |
|---|---|---|
| `title` | string | Announcement title. |
| `message` | string | Plain text message. |
| `buttonText` | string | Optional button label. |
| `buttonUrl` | string | Optional button URL. |
| `external` | bool | Whether the button is external. |

### Header

| Property | Type | Meaning |
|---|---|---|
| `showIcon` | bool | Show site icon. |
| `showTitle` | bool | Show site title. |
| `showSwitcher` | bool | Show language switcher. |
| `showSearch` | bool | Show search link. |
| `newTabIcon` | bool | Show new-tab icons. |
| `menu` | list<Link> | Header navigation. |

### Footer

| Property | Type | Meaning |
|---|---|---|
| `text` | string | Localized footer text. |
| `copyright` | string | Expanded copyright text. |
| `brand` | string | site, author or none. |
| `showLanguages` | bool | Show language links. |
| `showFeeds` | bool | Show feed links. |
| `showSite` | bool | Show footer navigation. |
| `menuHeading` | string | Footer navigation heading. |
| `newTabIcon` | bool | Show new-tab icons. |
| `menu` | list<Link> | Footer navigation. |

### Appearance

| Property | Type | Meaning |
|---|---|---|
| `scheme` | string | auto, light or dark. |
| `switchEnabled` | bool | Show scheme switcher. |
| `links` | array<string,Link> | Scheme links keyed by scheme. |
| `accentCssUrl` | string | Appearance CSS URL or empty. |
| `customCss` | bool | Whether custom CSS is configured. |

### Fonts

| Property | Type | Meaning |
|---|---|---|
| `source` | string | Resolved font source: system, local or google. |
| `preloads` | list<string> | Font preload URLs. |
| `cssUrl` | string | Font stylesheet URL or empty. |

### Home

| Property | Type | Meaning |
|---|---|---|
| `sections` | list<string> | Enabled section names in display order. |
| `hasPosts` | bool | Whether any posts exist. |
| `eyebrow` | string | Intro eyebrow. |
| `heading` | string | Intro heading. |
| `intro` | string | Plain intro text. |
| `buttons` | list<Link> | Intro buttons. |
| `decoration` | bool | Show decorative artwork. |

### Pagination

| Property | Type | Meaning |
|---|---|---|
| `current` | int | Current page number. |
| `total` | int | Total page count. |
| `previous` | ?string | Previous page URL. |
| `next` | ?string | Next page URL. |
| `pages` | list<Link> | Visible numbered page links. |

### ArchiveYear

| Property | Type | Meaning |
|---|---|---|
| `year` | string | Four-digit year. |
| `months` | list<ArchiveMonth> | Months with posts, newest first. |

### ArchiveMonth

| Property | Type | Meaning |
|---|---|---|
| `key` | string | Year and month, YYYY-MM. |
| `name` | string | Localized month name. |
| `posts` | list<Post> | Posts of this month on the current page. |

### Comments

| Property | Type | Meaning |
|---|---|---|
| `threads` | list<Comment> | Top-level comments. |
| `reactions` | list<Comment> | Webmention reactions. |
| `count` | int | Comment count. |
| `open` | bool | Whether the form is enabled. |
| `values` | array<string,string\|int> | Form fields: name, email, url, body, token, parent_id. |
| `error` | string | Translation key for form error. |
| `field` | string | Invalid field name. |
| `replyTo` | ?Comment | Reply target. |
| `notice` | string | pending, published or empty. |
| `action` | string | Form POST URL. |
| `url` | string | Post URL. |
| `maxLength` | int | Longest accepted comment, in characters. |

### Comment

| Property | Type | Meaning |
|---|---|---|
| `id` | int | Comment identifier. |
| `name` | string | Author name. |
| `url` | string | Validated author URL or empty. |
| `html` | string | Safe comment body HTML. |
| `created` | Date | Creation date. |
| `owner` | bool | Comment by site owner. |
| `kind` | string | comment or webmention. |
| `sourceUrl` | string | Webmention source URL. |
| `mentionType` | string | Webmention reaction type. |
| `replies` | list<Comment> | Replies. |

### CommentCheck

| Property | Type | Meaning |
|---|---|---|
| `values` | array<string,string\|int> | Preserved form fields. |
| `error` | string | Error translation key. |
| `action` | string | Form POST URL. |
| `postUrl` | string | Post URL. |
| `html` | string | Safe comment body HTML. |
| `widget` | string | CAPTCHA widget class. |
| `siteKey` | string | Public CAPTCHA key. |
| `script` | string | CAPTCHA script URL. |

### SearchResult

| Property | Type | Meaning |
|---|---|---|
| `url` | string | Result URL. |
| `title` | string | Result title. |
| `published` | ?Date | Publication date when available. |
| `html` | string | Safe highlighted snippet HTML. |

