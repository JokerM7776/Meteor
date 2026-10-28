<?php
declare(strict_types=1);
namespace App\Theme;

/** Theme API v1: ArchiveMonth. */
final readonly class ArchiveMonth
{
    public function __construct(
        /** Year and month, YYYY-MM. */
        public string $key,
        /** Localized month name. */
        public string $name,
        /** list<Post> Posts of this month on the current page. */
        public array $posts
    ) {}
}
