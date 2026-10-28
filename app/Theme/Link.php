<?php
declare(strict_types=1);
namespace App\Theme;

/** Theme API v1: Link. */
final readonly class Link
{
    public function __construct(
        /** Link label. */
        public string $label,
        /** Destination URL. */
        public string $url,
        /** Current navigation item. */
        public bool $current,
        /** External URL. */
        public bool $external,
        /** Open in a new tab. */
        public bool $newTab,
        /** Email link. */
        public bool $email
    ) {}
}
