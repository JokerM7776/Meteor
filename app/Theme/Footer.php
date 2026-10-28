<?php
declare(strict_types=1);
namespace App\Theme;

/** Theme API v1: Footer. */
final readonly class Footer
{
    public function __construct(
        /** Localized footer text. */
        public string $text,
        /** Expanded copyright text. */
        public string $copyright,
        /** site, author or none. */
        public string $brand,
        /** Show language links. */
        public bool $showLanguages,
        /** Show feed links. */
        public bool $showFeeds,
        /** Show footer navigation. */
        public bool $showSite,
        /** Footer navigation heading. */
        public string $menuHeading,
        /** Show new-tab icons. */
        public bool $newTabIcon,
        /** list<Link> Footer navigation. */
        public array $menu
    ) {}
}
