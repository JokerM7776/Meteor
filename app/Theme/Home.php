<?php
declare(strict_types=1);
namespace App\Theme;

/** Theme API v1: Home. */
final readonly class Home
{
    public function __construct(
        /** list<string> Enabled section names in display order. */
        public array $sections,
        /** Whether any posts exist. */
        public bool $hasPosts,
        /** Intro eyebrow. */
        public string $eyebrow,
        /** Intro heading. */
        public string $heading,
        /** Plain intro text. */
        public string $intro,
        /** list<Link> Intro buttons. */
        public array $buttons,
        /** Show decorative artwork. */
        public bool $decoration
    ) {}
}
