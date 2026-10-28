<?php
declare(strict_types=1);
namespace App\Theme;

/** Theme API v1: Site. */
final readonly class Site
{
    public function __construct(
        /** Localized site title. */
        public string $title,
        /** Localized tagline. */
        public string $tagline,
        /** Absolute site base URL. */
        public string $url,
        /** Site icon chosen in Settings; null when none is set. */
        public ?Image $icon,
        /** Browser icon: the site icon, or Meteor's default icon. */
        public Image $favicon,
        /** Current language. */
        public Language $locale,
        /** array<string,Language> Switcher languages keyed by code. */
        public array $languages,
        /** array<string,Language> Public (live) languages keyed by code; their url is their home page. */
        public array $liveLanguages,
        /** Default language. */
        public Language $defaultLanguage,
        /** Header configuration. */
        public Header $header,
        /** Footer configuration. */
        public Footer $footer,
        /** Author profile. */
        public ?Author $author,
        /** Whether posts display the author card. */
        public bool $postAuthorCard,
        /** list<Announcement> Visible announcements. */
        public array $announcements,
        /** Color appearance configuration. */
        public Appearance $appearance,
        /** Resolved font resources. */
        public Fonts $fonts,
        /** Current language feed URL; empty in preview locales. */
        public string $feedUrl,
        /** Search URL. */
        public string $searchUrl,
        /** Home URL. */
        public string $homeUrl,
        /** Post archive URL. */
        public string $postsUrl
    ) {}
}
