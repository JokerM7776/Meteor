<?php
declare(strict_types=1);
return [
    ['building-a-zero-js-blog', 'Building a zero-JS blog with vanilla PHP', 'One entry file, a regex router, PDO and output buffering. How far plain PHP 8 gets you before you miss a framework.', '2026-10-12', 'engineering', ['php', 'architecture', 'zero-js'], <<<'MD'
Most blogs ship more JavaScript than words. This one ships none. The public pages are rendered by plain PHP, the CSS fits in a single file, and a post loads in one request.

Here is how the pieces fit together, and where the edges are.

## One entry point
Every request is rewritten to `public/index.php`. The router reads the path, peels off the language prefix and matches the rest against a short list of patterns.

```php
// Pull the locale off the front of the path
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (isset($registry[$prefix])) {
    $locale = $prefix;
    $rest = $segments[1] ?? '/';
}
```

### PHP is already a template engine
Views are plain `.php` files. Output buffering captures a page view, and the master layout echoes it between the header and footer partials.

> The fastest code on a page load is the code that already ran when you pressed Save.

## Compile on save
Markdown is converted to HTML once. The public site only reads three columns:

- **content_html** — the article body, ready to echo
- **toc_html** — the anchor list you see on the right
- **summary** — the excerpt and meta description

## One post, many languages
A post is one row in *posts* and one row per language in *post_translations*. Published translations become links at the top of the page and hreflang tags in its head.
MD],
    ['compile-markdown-on-save', 'Pre-compiling Markdown on save, not on read', 'Store content_html next to content_md and the public site does nothing but echo a column.', '2026-10-08', 'engineering', ['php', 'markdown'], <<<'MD'
Rendering the same Markdown for every reader wastes work. Saving is the right moment to prepare the page.

## Compile once
Keep the original source beside the compiled output so editing remains straightforward.

```php
$compiled = $markdown->compile($source);
$contentHtml = $compiled['html'];
```

## Save together
1. Validate the source.
2. Compile the article and its table of contents.
3. Store both in the same transaction.

> A reader should never have to wait for work the author could do once.
MD],
    ['markdown-parser-150-lines', 'A 150-line Markdown parser, line by line', 'Headings, emphasis, links, lists and fenced code — plus the table of contents, extracted in the same pass.', '2026-09-30', 'engineering', ['php', 'markdown'], <<<'MD'
A small Markdown subset is enough for notes and tutorials. Its boundaries should be explicit.

## Start with blocks
Recognize headings, lists, quotes and fenced code before inline emphasis.

```php
foreach ($lines as $line) {
    $html .= renderBlock($line);
}
```

### Escape the input
Raw HTML remains text. This keeps the public rendering contract simple.

- Give each heading a stable identifier.
- Preserve Unicode letters.
- Collect the table of contents during compilation.

> A small parser is useful when its limitations are clear.
MD],
    ['one-url-per-translation', 'Why every translation needs its own URL', 'Language prefixes, hreflang pairs and a sitemap that tells search engines which pages are the same article.', '2026-09-28', 'seo', ['i18n', 'architecture'], <<<'MD'
A translation deserves a stable address that readers can bookmark and share.

## Make the language explicit
Use a language prefix and a shared article slug. The database holds the relationship between translations.

```html
<link rel="alternate" hreflang="en" href="https://example.test/en/">
```

## Describe the relationship
- Give every translation its own canonical URL.
- Link all published live translations together.
- Include the same relationships in the sitemap.

> The URL should describe the page a reader will receive.
MD],
    ['dark-mode-one-media-query', 'Dark mode with one media query', 'prefers-color-scheme, a dozen custom properties, and no toggle button to maintain.', '2026-09-21', 'css', ['dark-mode', 'zero-js'], <<<'MD'
The browser already knows the reader’s preferred color scheme. Let CSS respond to it.

## Start with readable colors
```css
@media (prefers-color-scheme: dark) {
    body { background: #020617; color: #e2e8f0; }
}
```

## Check more than the background
- Keep muted text readable.
- Adjust borders and code blocks.
- Preserve a visible keyboard focus ring.

> Dark mode is a complete palette, not an inverted screenshot.
MD],
    ['kl-three-hawker-stalls', 'Kuala Lumpur, in three hawker stalls', 'A short walk from Jalan Alor to Petaling Street, written first in Malay and translated after.', '2026-09-14', 'travel', ['malaysia'], <<<'MD'
The city changes as the evening stalls open. A short walk becomes a long dinner.

## Start on Jalan Alor
Look for the steam rising from a busy wok and leave time to sit down.

## Walk toward Petaling Street
1. Order something small.
2. Share a table and a conversation.
3. Leave room for the next stop.

> A city is easier to understand at walking speed.

### Keep a note
```bash
# Save a reminder for the next walk
echo "Bring an umbrella and arrive hungry."
```
MD],
    ['hamburger-menu-no-js', 'Hamburger menus without JavaScript', 'The details element versus the checkbox hack, and why one of them is accessible by default.', '2026-09-02', 'css', ['zero-js', 'architecture'], <<<'MD'
A compact menu does not need a client-side library. HTML includes a disclosure control.

## Use the native element
```html
<details>
  <summary>Menu</summary>
  <nav><a href="/en/posts">Posts</a></nav>
</details>
```

## Make room for interaction
- Keep touch targets large enough.
- Give the summary a useful label.
- Test with a keyboard and a narrow screen.

> Native controls carry behavior that custom widgets must recreate.
MD],
];
