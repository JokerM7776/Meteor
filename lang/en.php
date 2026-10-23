<?php
declare(strict_types=1);

// Built-in English interface text for public pages. Every key here is listed in one group of
// App\Services\UiStrings::GROUPS; translations live in lang/<code>.json and the ui_strings table.
return [
    // Layout & navigation
    'skip_content' => 'Skip to content',
    'site_name' => 'Meteor',
    'menu' => 'Menu',
    'language' => 'Language',
    'home' => 'Home',
    'posts' => 'Posts',
    'tags' => 'Tags',
    'about' => 'About',
    'rss' => 'RSS',
    'sitemap' => 'Sitemap',
    'site' => 'Site',
    'breadcrumb' => 'Breadcrumb',
    'pagination' => 'Pagination',
    // Footer
    'footer_description' => 'Server-rendered HTML and CSS. Zero JavaScript on public pages.',
    'read_in' => 'Read in',
    'feeds' => 'Feeds',
    'language_rss' => '{language} RSS feed',
    // Home
    'home_heading' => 'Notes that load before you blink.',
    'home_intro' => 'Meteor is a small, server-rendered blog. Every page is plain HTML and CSS — no scripts, no trackers, nothing to wait for.',
    'pinned' => 'Pinned',
    'latest' => 'Latest',
    'view_all_posts' => 'View all posts',
    'read_archive' => 'Read the archive',
    'empty_posts' => 'No posts yet.',
    // Articles & pages
    'published' => 'Published',
    'reading_minutes' => '{minutes} min read',
    'on_this_page' => 'On this page',
    'translation' => 'Translation',
    'available_translations' => 'This article is also available in',
    'category' => 'Category',
    'more_posts' => 'More posts',
    'previous' => 'Previous',
    'next' => 'Next',
    // Archive
    'archive' => 'Archive',
    'archive_description' => 'Everything published in {language}, newest first.',
    'all' => 'All',
    'newer' => 'Newer',
    'older' => 'Older',
    // Page not found
    'not_found' => 'Page not found',
    'not_found_heading' => 'This page burned up on entry.',
    'not_found_description' => 'The link may be old, or the post was never written in {language}. Try the home page, or browse the archive.',
    'back_home' => 'Back to home',
    'browse_archive' => 'Browse the archive',
];
