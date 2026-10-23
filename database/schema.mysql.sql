-- Meteor foundation schema. Timestamps are UTC.
-- Only one default locale is maintained by the locale service/installer.
-- credential_hash is SHA-256 of credential_id, avoiding oversized MySQL indexes.

CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(191) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    session_version INTEGER NOT NULL DEFAULT 1,
    password_changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE passkeys (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    user_id INTEGER NOT NULL,
    credential_id VARCHAR(1024) NOT NULL,
    credential_hash CHAR(64) COLLATE utf8mb4_bin NOT NULL UNIQUE,
    public_key TEXT NOT NULL,
    algorithm INTEGER NOT NULL CHECK (algorithm IN (-7, -257)),
    sign_count BIGINT NOT NULL DEFAULT 0 CHECK (sign_count >= 0),
    name VARCHAR(191) NOT NULL,
    transports TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    ip_address VARCHAR(45) NOT NULL,
    username VARCHAR(191) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE locales (
    code VARCHAR(35) COLLATE utf8mb4_bin PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    native_name VARCHAR(191) NOT NULL,
    status VARCHAR(10) NOT NULL DEFAULT 'preview' CHECK (status IN ('live', 'preview', 'off')),
    is_default INTEGER NOT NULL DEFAULT 0 CHECK (is_default IN (0, 1)),
    sort_order INTEGER NOT NULL DEFAULT 0,
    direction VARCHAR(3) NOT NULL DEFAULT 'ltr' CHECK (direction IN ('ltr', 'rtl')),
    CHECK (is_default = 0 OR status = 'live')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ui_strings (
    locale VARCHAR(35) COLLATE utf8mb4_bin NOT NULL,
    `key` VARCHAR(191) COLLATE utf8mb4_bin NOT NULL,
    value TEXT NOT NULL,
    PRIMARY KEY (locale, `key`),
    FOREIGN KEY (locale) REFERENCES locales(code) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    `key` VARCHAR(191) COLLATE utf8mb4_bin NOT NULL,
    locale VARCHAR(35) COLLATE utf8mb4_bin,
    locale_scope VARCHAR(35) COLLATE utf8mb4_bin GENERATED ALWAYS AS (COALESCE(locale, '')) STORED,
    value TEXT NOT NULL,
    UNIQUE (`key`, locale_scope),
    FOREIGN KEY (locale) REFERENCES locales(code) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    slug VARCHAR(191) COLLATE utf8mb4_bin NOT NULL UNIQUE,
    sort_order INTEGER NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE category_translations (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    category_id INTEGER NOT NULL,
    locale VARCHAR(35) COLLATE utf8mb4_bin NOT NULL,
    name VARCHAR(191) NOT NULL,
    description TEXT,
    UNIQUE (category_id, locale),
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    FOREIGN KEY (locale) REFERENCES locales(code) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tags (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    slug VARCHAR(191) COLLATE utf8mb4_bin NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tag_translations (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    tag_id INTEGER NOT NULL,
    locale VARCHAR(35) COLLATE utf8mb4_bin NOT NULL,
    name VARCHAR(191) NOT NULL,
    UNIQUE (tag_id, locale),
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
    FOREIGN KEY (locale) REFERENCES locales(code) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE posts (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    slug VARCHAR(191) COLLATE utf8mb4_bin NOT NULL UNIQUE,
    category_id INTEGER,
    base_locale VARCHAR(35) COLLATE utf8mb4_bin NOT NULL,
    cover_image VARCHAR(2048),
    is_published INTEGER NOT NULL DEFAULT 0 CHECK (is_published IN (0, 1)),
    is_pinned INTEGER NOT NULL DEFAULT 0 CHECK (is_pinned IN (0, 1)),
    published_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (base_locale) REFERENCES locales(code) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE post_translations (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    post_id INTEGER NOT NULL,
    locale VARCHAR(35) COLLATE utf8mb4_bin NOT NULL,
    title VARCHAR(255) NOT NULL,
    summary TEXT NOT NULL,
    content_md LONGTEXT NOT NULL,
    content_html LONGTEXT NOT NULL,
    toc_html TEXT NOT NULL,
    status VARCHAR(10) NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'published')),
    published_at DATETIME,
    reading_minutes INTEGER NOT NULL DEFAULT 1 CHECK (reading_minutes >= 1),
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (post_id, locale),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (locale) REFERENCES locales(code) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE post_tags (
    post_id INTEGER NOT NULL,
    tag_id INTEGER NOT NULL,
    PRIMARY KEY (post_id, tag_id),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pages (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    slug VARCHAR(191) COLLATE utf8mb4_bin NOT NULL UNIQUE,
    base_locale VARCHAR(35) COLLATE utf8mb4_bin NOT NULL,
    is_published INTEGER NOT NULL DEFAULT 0 CHECK (is_published IN (0, 1)),
    sort_order INTEGER NOT NULL DEFAULT 0,
    show_in_nav INTEGER NOT NULL DEFAULT 0 CHECK (show_in_nav IN (0, 1)),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (base_locale) REFERENCES locales(code) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE page_translations (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    page_id INTEGER NOT NULL,
    locale VARCHAR(35) COLLATE utf8mb4_bin NOT NULL,
    title VARCHAR(255) NOT NULL,
    nav_label VARCHAR(191),
    summary TEXT NOT NULL,
    content_md LONGTEXT NOT NULL,
    content_html LONGTEXT NOT NULL,
    toc_html TEXT NOT NULL,
    status VARCHAR(10) NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'published')),
    published_at DATETIME,
    reading_minutes INTEGER NOT NULL DEFAULT 1 CHECK (reading_minutes >= 1),
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (page_id, locale),
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
    FOREIGN KEY (locale) REFERENCES locales(code) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE menu_items (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    menu VARCHAR(10) NOT NULL CHECK (menu IN ('header', 'footer')),
    kind VARCHAR(10) NOT NULL CHECK (kind IN ('posts', 'tags', 'about', 'pages', 'rss', 'sitemap', 'link')),
    url VARCHAR(2048),
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_visible INTEGER NOT NULL DEFAULT 1 CHECK (is_visible IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE menu_item_labels (
    item_id INTEGER NOT NULL,
    locale VARCHAR(35) COLLATE utf8mb4_bin NOT NULL,
    label VARCHAR(191) NOT NULL,
    PRIMARY KEY (item_id, locale),
    FOREIGN KEY (item_id) REFERENCES menu_items(id) ON DELETE CASCADE,
    FOREIGN KEY (locale) REFERENCES locales(code) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
