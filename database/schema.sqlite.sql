-- Meteor foundation schema. Timestamps are UTC.
-- Only one default locale is maintained by the locale service/installer.
-- credential_hash is SHA-256 of credential_id, avoiding oversized MySQL indexes.

CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(191) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    session_version INTEGER NOT NULL DEFAULT 1,
    password_changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE passkeys (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    credential_id VARCHAR(1024) NOT NULL,
    credential_hash CHAR(64) NOT NULL UNIQUE,
    public_key TEXT NOT NULL,
    algorithm INTEGER NOT NULL CHECK (algorithm IN (-7, -257)),
    sign_count BIGINT NOT NULL DEFAULT 0 CHECK (sign_count >= 0),
    name VARCHAR(191) NOT NULL,
    transports TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE remember_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    selector CHAR(24) NOT NULL UNIQUE,
    validator_hash CHAR(64) NOT NULL,
    session_version INTEGER NOT NULL,
    user_agent VARCHAR(255) NOT NULL DEFAULT '',
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip_address VARCHAR(45) NOT NULL,
    username VARCHAR(191) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE locales (
    code VARCHAR(35) PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    native_name VARCHAR(191) NOT NULL,
    status VARCHAR(10) NOT NULL DEFAULT 'preview' CHECK (status IN ('live', 'preview', 'off')),
    is_default INTEGER NOT NULL DEFAULT 0 CHECK (is_default IN (0, 1)),
    sort_order INTEGER NOT NULL DEFAULT 0,
    direction VARCHAR(3) NOT NULL DEFAULT 'ltr' CHECK (direction IN ('ltr', 'rtl')),
    CHECK (is_default = 0 OR status = 'live')
);

CREATE TABLE ui_strings (
    locale VARCHAR(35) NOT NULL,
    `key` VARCHAR(191) NOT NULL,
    value TEXT NOT NULL,
    PRIMARY KEY (locale, `key`),
    FOREIGN KEY (locale) REFERENCES locales(code) ON DELETE CASCADE
);

CREATE TABLE settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    `key` VARCHAR(191) NOT NULL,
    locale VARCHAR(35),
    locale_scope VARCHAR(35) GENERATED ALWAYS AS (COALESCE(locale, '')) STORED,
    value TEXT NOT NULL,
    UNIQUE (`key`, locale_scope),
    FOREIGN KEY (locale) REFERENCES locales(code) ON DELETE CASCADE
);

CREATE TABLE categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug VARCHAR(191) NOT NULL UNIQUE,
    sort_order INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE category_translations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category_id INTEGER NOT NULL,
    locale VARCHAR(35) NOT NULL,
    name VARCHAR(191) NOT NULL,
    description TEXT,
    UNIQUE (category_id, locale),
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    FOREIGN KEY (locale) REFERENCES locales(code) ON DELETE CASCADE
);

CREATE TABLE tags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug VARCHAR(191) NOT NULL UNIQUE
);

CREATE TABLE tag_translations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tag_id INTEGER NOT NULL,
    locale VARCHAR(35) NOT NULL,
    name VARCHAR(191) NOT NULL,
    UNIQUE (tag_id, locale),
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
    FOREIGN KEY (locale) REFERENCES locales(code) ON DELETE CASCADE
);

CREATE TABLE posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug VARCHAR(191) NOT NULL UNIQUE,
    category_id INTEGER,
    base_locale VARCHAR(35) NOT NULL,
    cover_image VARCHAR(2048),
    is_published INTEGER NOT NULL DEFAULT 0 CHECK (is_published IN (0, 1)),
    is_pinned INTEGER NOT NULL DEFAULT 0 CHECK (is_pinned IN (0, 1)),
    markdown_extended INTEGER NOT NULL DEFAULT 0 CHECK (markdown_extended IN (0, 1)),
    comments_enabled INTEGER NOT NULL DEFAULT 1 CHECK (comments_enabled IN (0, 1)),
    published_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (base_locale) REFERENCES locales(code) ON DELETE RESTRICT
);

CREATE TABLE post_translations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    locale VARCHAR(35) NOT NULL,
    title VARCHAR(255) NOT NULL,
    summary TEXT NOT NULL,
    content_md TEXT NOT NULL,
    content_html TEXT NOT NULL,
    toc_html TEXT NOT NULL,
    status VARCHAR(10) NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'published')),
    published_at DATETIME,
    reading_minutes INTEGER NOT NULL DEFAULT 1 CHECK (reading_minutes >= 1),
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (post_id, locale),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (locale) REFERENCES locales(code) ON DELETE CASCADE
);

CREATE TABLE post_tags (
    post_id INTEGER NOT NULL,
    tag_id INTEGER NOT NULL,
    PRIMARY KEY (post_id, tag_id),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
);

CREATE TABLE pages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug VARCHAR(191) NOT NULL UNIQUE,
    base_locale VARCHAR(35) NOT NULL,
    is_published INTEGER NOT NULL DEFAULT 0 CHECK (is_published IN (0, 1)),
    sort_order INTEGER NOT NULL DEFAULT 0,
    markdown_extended INTEGER NOT NULL DEFAULT 0 CHECK (markdown_extended IN (0, 1)),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (base_locale) REFERENCES locales(code) ON DELETE RESTRICT
);

CREATE TABLE page_translations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    page_id INTEGER NOT NULL,
    locale VARCHAR(35) NOT NULL,
    title VARCHAR(255) NOT NULL,
    summary TEXT NOT NULL,
    content_md TEXT NOT NULL,
    content_html TEXT NOT NULL,
    toc_html TEXT NOT NULL,
    status VARCHAR(10) NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'published')),
    published_at DATETIME,
    reading_minutes INTEGER NOT NULL DEFAULT 1 CHECK (reading_minutes >= 1),
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (page_id, locale),
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
    FOREIGN KEY (locale) REFERENCES locales(code) ON DELETE CASCADE
);

CREATE TABLE menu_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    menu VARCHAR(10) NOT NULL CHECK (menu IN ('header', 'footer')),
    kind VARCHAR(10) NOT NULL CHECK (kind IN ('posts', 'tags', 'about', 'pages', 'rss', 'sitemap', 'link')),
    url VARCHAR(2048),
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_visible INTEGER NOT NULL DEFAULT 1 CHECK (is_visible IN (0, 1)),
    page_id INTEGER NULL,
    new_tab INTEGER NOT NULL DEFAULT 0 CHECK (new_tab IN (0, 1))
);

CREATE TABLE menu_item_labels (
    item_id INTEGER NOT NULL,
    locale VARCHAR(35) NOT NULL,
    label VARCHAR(191) NOT NULL,
    PRIMARY KEY (item_id, locale),
    FOREIGN KEY (item_id) REFERENCES menu_items(id) ON DELETE CASCADE,
    FOREIGN KEY (locale) REFERENCES locales(code) ON DELETE CASCADE
);

CREATE TABLE media (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    file_name VARCHAR(80) NOT NULL UNIQUE,
    thumb_name VARCHAR(80),
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(50) NOT NULL,
    width INTEGER NOT NULL DEFAULT 0,
    height INTEGER NOT NULL DEFAULT 0,
    size_bytes INTEGER NOT NULL DEFAULT 0,
    alt_text VARCHAR(255) NOT NULL DEFAULT '',
    category VARCHAR(191) NOT NULL DEFAULT '',
    search_text TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE media_tags (
    media_id INTEGER NOT NULL,
    tag VARCHAR(191) NOT NULL,
    PRIMARY KEY (media_id, tag),
    FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE
);

CREATE TABLE announcements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title VARCHAR(191) NOT NULL DEFAULT '',
    message TEXT NOT NULL,
    button_text VARCHAR(80) NOT NULL DEFAULT '',
    button_url VARCHAR(2048) NOT NULL DEFAULT '',
    locale_codes VARCHAR(1000) NOT NULL DEFAULT '',
    is_visible INTEGER NOT NULL DEFAULT 1 CHECK (is_visible IN (0, 1)),
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    locale VARCHAR(35) NOT NULL,
    parent_id INTEGER,
    kind VARCHAR(10) NOT NULL DEFAULT 'comment' CHECK (kind IN ('comment', 'webmention')),
    mention_type VARCHAR(10) NOT NULL DEFAULT '',
    author_name VARCHAR(100) NOT NULL,
    author_email VARCHAR(191) NOT NULL DEFAULT '',
    author_url VARCHAR(2048) NOT NULL DEFAULT '',
    body TEXT NOT NULL,
    source_url VARCHAR(2048) NOT NULL DEFAULT '',
    status VARCHAR(10) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'spam')),
    is_owner INTEGER NOT NULL DEFAULT 0 CHECK (is_owner IN (0, 1)),
    ip_hash CHAR(64) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (locale) REFERENCES locales(code) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE
);

CREATE TABLE webmentions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    direction VARCHAR(3) NOT NULL CHECK (direction IN ('in', 'out')),
    source VARCHAR(2048) NOT NULL,
    target VARCHAR(2048) NOT NULL,
    pair_hash CHAR(64) NOT NULL UNIQUE,
    status VARCHAR(10) NOT NULL DEFAULT 'queued' CHECK (status IN ('queued', 'done', 'failed')),
    attempts INTEGER NOT NULL DEFAULT 0,
    result VARCHAR(255) NOT NULL DEFAULT '',
    ip_hash CHAR(64) NOT NULL DEFAULT '',
    next_try_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX login_attempts_ip_time ON login_attempts (ip_address, attempted_at);
CREATE INDEX login_attempts_user_time ON login_attempts (username, attempted_at);
CREATE INDEX locales_status_order ON locales (status, sort_order);
CREATE INDEX posts_publication ON posts (is_published, published_at);
CREATE INDEX posts_pinned ON posts (is_pinned, published_at);
CREATE INDEX post_translations_locale_status ON post_translations (locale, status);
CREATE INDEX page_translations_locale_status ON page_translations (locale, status);
CREATE INDEX post_tags_tag ON post_tags (tag_id);
CREATE INDEX category_translations_locale ON category_translations (locale);
CREATE INDEX tag_translations_locale ON tag_translations (locale);
CREATE INDEX menu_items_menu_order ON menu_items (menu, sort_order);
CREATE INDEX media_created ON media (created_at, id);
CREATE INDEX media_category ON media (category);
CREATE INDEX media_tags_tag ON media_tags (tag);
CREATE INDEX comments_post ON comments (post_id, locale, status);
CREATE INDEX comments_status ON comments (status, created_at);
CREATE INDEX comments_ip ON comments (ip_hash, created_at);
CREATE INDEX webmentions_queue ON webmentions (status, next_try_at);
CREATE INDEX webmentions_ip ON webmentions (ip_hash, created_at);
