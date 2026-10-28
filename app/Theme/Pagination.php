<?php
declare(strict_types=1);
namespace App\Theme;

/** Theme API v1: Pagination. */
final readonly class Pagination
{
    public function __construct(
        /** Current page number. */
        public int $current,
        /** Total page count. */
        public int $total,
        /** Previous page URL. */
        public ?string $previous,
        /** Next page URL. */
        public ?string $next,
        /** list<Link> Visible numbered page links. */
        public array $pages
    ) {}
}
