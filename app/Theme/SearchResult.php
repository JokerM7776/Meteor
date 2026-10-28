<?php
declare(strict_types=1);
namespace App\Theme;

/** Theme API v1: SearchResult. */
final readonly class SearchResult
{
    public function __construct(
        /** Result URL. */
        public string $url,
        /** Result title. */
        public string $title,
        /** Publication date when available. */
        public ?Date $published,
        /** Safe highlighted snippet HTML. */
        public string $html
    ) {}
}
