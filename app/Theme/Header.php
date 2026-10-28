<?php
declare(strict_types=1);
namespace App\Theme;

/** Theme API v1: Header. */
final readonly class Header
{
    public function __construct(
        /** Show site icon. */
        public bool $showIcon,
        /** Show site title. */
        public bool $showTitle,
        /** Show language switcher. */
        public bool $showSwitcher,
        /** Show search link. */
        public bool $showSearch,
        /** Show new-tab icons. */
        public bool $newTabIcon,
        /** list<Link> Header navigation. */
        public array $menu
    ) {}
}
