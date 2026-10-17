# Multilingual Blog Architecture Specification
**Stack:** Vanilla PHP (Zero Frameworks) + Zero-JS HTML/CSS Frontend

Building a multilingual blog system with **Vanilla PHP (Zero Frameworks)** and a **Zero-JS Frontend** gives you an ultra-fast, lightweight application with zero dependency bloat, near-instant server response times, and effortless deployment across any standard web host or container environment.

---

## 1. Core Page Architecture

To balance reader performance, search engine visibility, and authoring convenience, the system is divided into three distinct layers.

### A. Public Frontend (Server-Rendered HTML + CSS, Zero JS)
All public routes should use a consistent language prefix (e.g., `/en/posts/...`, `/zh/posts/...`) for clean routing and optimal SEO indexing.

| Page Name | Route Pattern | Key Features & Design Requirements |
| :--- | :--- | :--- |
| **Home** | `/{lang}/` | Displays site intro/welcome message, pinned posts, a concise list of latest posts, and a prominent language switcher. |
| **Post Index / Archive** | `/{lang}/posts` | Supports standard URL pagination (`?page=2`, requiring no JS), timeline archiving, and pure-link filtering by Categories and Tags. |
| **Post Detail** | `/{lang}/posts/{slug}` | Renders pre-compiled HTML content, an auto-generated Table of Contents (TOC using native `#anchor` links), estimated reading time, and Next/Previous post navigation. **Crucial Feature:** Direct links at the top to switch to other available language translations of the exact same article. |
| **Taxonomy Archive** | `/{lang}/tags/{tag}` | Displays all published articles under a specific tag or category for the active language. |
| **Static Pages (About, etc.)** | `/{lang}/about` | Powered by a generic "Custom Pages" engine, allowing you to create standalone pages like *About*, *Projects*, or *Links* in multiple languages. |
| **404 / Error Page** | `/{lang}/404` | Automatically matches the language prefix in the URL to display localized error messages and a link back to the home page. |

### B. System & SEO Endpoints (Machine-Readable)
* **Multilingual Sitemap (`/sitemap.xml`):** Dynamically generated via PHP with `header('Content-Type: application/xml')`. It must include `<xhtml:link rel="alternate" hreflang="..." />` tags to inform search engines how translated versions of the same post map to one another.
* **Localized RSS Feeds (`/{lang}/rss.xml`):** Splits feed outputs by language so English subscribers only receive English updates and Chinese subscribers only receive Chinese updates.

### C. Admin Dashboard (Authoring Layer — Minimal JS Allowed)
To make multilingual creation effortless, the backend focuses on authoring workflow:
* **Authentication (`/admin/login`):** Clean, session-based login protected by native PHP `password_hash()` and CSRF tokens.
* **Translation Matrix (`/admin/posts`):** Lists all articles with visual status badges indicating available translations (e.g., `[EN ✅] [ZH ✅] [MS ➕]`). Clicking the `+` icon opens the editor directly for that missing language.
* **Side-by-Side Multilingual Editor (`/admin/editor`):** **The core feature.** A split-screen layout where the left pane displays the original base-language article for reference, and the right pane provides the Markdown editor for the target translation.
* **Site & Dictionary Settings (`/admin/settings`):** Manages supported locales, the default fallback language, localized site titles/navigation labels, and custom CSS injection.

---

## 2. Technical Architecture (Vanilla PHP + Zero-JS Frontend)

Eliminating both frontend JS and backend frameworks means relying on native browser capabilities and PHP's built-in execution speed.

### A. Zero-JS Frontend Techniques
Features that traditionally rely on JavaScript are shifted to server-side pre-processing or modern HTML5/CSS3:

* **Mobile Navigation & Collapsible UI:** Use native HTML `<details>` and `<summary>` tags, or the "Checkbox Hack" (`<input type="checkbox">` paired with CSS `:checked` sibling selectors) to build responsive mobile hamburger menus with zero JS.
* **Dark Mode:** Use the CSS media query `@media (prefers-color-scheme: dark)` combined with CSS Custom Properties (variables) to automatically adapt to the reader's OS theme.
* **Syntax Highlighting:** Instead of loading `Prism.js` on the client, parse code blocks on the server when saving the post (or style standard `<pre><code>` blocks cleanly with lightweight CSS).
* **Styling:** Write a single, clean `style.css` file using modern CSS Grid/Flexbox, or use a minimal classless CSS stylesheet (like Pico.css) to keep the entire CSS payload under 10KB.

### B. Vanilla PHP Backend Design (Zero External Frameworks)
Without Laravel or Slim, you can structure a clean, maintainable micro-architecture using pure PHP 8.x:

1. **Single Entrypoint & Routing (`public/index.php`):**
   Use Apache `.htaccess` or Nginx `try_files` to route all requests to `index.php`. Parse `$_SERVER['REQUEST_URI']` using `parse_url()` and `preg_match()` to extract the `{lang}` prefix and route parameters, dispatching them to the appropriate PHP controller script.
2. **Native PHP Templating:**
   PHP *is* a template engine by nature. Create reusable layout partials (`views/header.php`, `views/footer.php`) and use output buffering (`ob_start()`, `ob_get_clean()`) to inject page views into a master layout file cleanly. Always sanitize dynamic output with `htmlspecialchars()` to prevent XSS.
3. **Database Layer (`PDO`):**
   Use PHP's native `PDO` extension with prepared statements (`$pdo->prepare()`) to interact with **MariaDB/MySQL** or **SQLite**. This guarantees security against SQL injection without needing a heavy ORM.
4. **Zero-Dependency Markdown & TOC Parsing:**
   Instead of pulling in external Composer packages, write a lightweight ~150-line PHP Regex parser class (`MarkdownParser.php`) that converts standard Markdown syntax (headers, bold, links, lists, code blocks) into HTML on save, while simultaneously extracting `<h2>` and `<h3>` tags to generate an HTML Table of Contents.
5. **Localization (i18n) Dictionary:**
   Store static UI translations (e.g., "Read More", "Published on", "Categories") in simple PHP associative arrays (`lang/en.php`, `lang/zh.php`) and wrap them in a tiny helper function like `__('read_more')`.

```text
/project-root
 ├── /config          # Database credentials & supported locales array
 ├── /core            # Custom Router.php, Database.php (PDO), Markdown.php
 ├── /lang            # UI translation arrays (en.php, zh.php, ms.php)
 ├── /views           # Pure PHP/HTML templates (/public and /admin)
 └── /public          # Web root (index.php, style.css, /uploads)
```

---

## 3. Multilingual Database Schema

To make multilingual publishing seamless, avoid treating translated posts as separate, unrelated articles. Use a **Master-Translation Relational Pattern**:

### 1. `posts` (Master Table — Language-Agnostic Metadata)
Stores shared properties so you only have to upload cover images or assign categories once per article.
* `id` (INT, Primary Key, Auto Increment)
* `slug` (VARCHAR, Unique — e.g., `building-a-zero-js-blog`)
* `category_id` (INT, Foreign Key)
* `cover_image` (VARCHAR, Nullable)
* `is_published` (TINYINT/BOOLEAN — Global visibility toggle)
* `created_at` (DATETIME)

### 2. `post_translations` (Translation Table — Localized Content)
Stores the actual text for each language.
* `id` (INT, Primary Key, Auto Increment)
* `post_id` (INT, Foreign Key referencing `posts.id` ON DELETE CASCADE)
* `locale` (VARCHAR(10) — e.g., `en`, `zh`, `ms`. **Unique composite index on `(post_id, locale)`**)
* `title` (VARCHAR — Localized title)
* `summary` (TEXT — Localized excerpt & SEO meta description)
* `content_md` (LONGTEXT — Raw Markdown used when editing in the admin panel)
* `content_html` (LONGTEXT — **Pre-compiled HTML**. Generated by PHP when you hit "Save" in the admin panel. The public frontend queries and echoes this column directly, resulting in near-zero CPU overhead on page loads)
* `toc_html` (TEXT — Pre-compiled HTML anchor list for the Table of Contents)
* `status` (ENUM: `'draft'`, `'published'`)
* `updated_at` (DATETIME)

### Why This Schema Works So Well:
When a reader requests `/en/posts/building-a-zero-js-blog`, your Vanilla PHP script runs a fast query to fetch the `en` row from `post_translations`, plus a lightweight secondary query (`SELECT locale FROM post_translations WHERE post_id = ? AND status = 'published'`) to immediately know which other languages exist for this exact article. It then renders the `<link rel="alternate" hreflang="...">` tags in the `<head>` and builds the language switcher buttons at the top of the article.