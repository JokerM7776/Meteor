<?php
declare(strict_types=1);
namespace App\Theme;

/** Theme API v1: Author. */
final readonly class Author
{
    public function __construct(
        /** Display name. */
        public string $name,
        /** Localized biography. */
        public string $bio,
        /** Profile image. */
        public ?Image $avatar,
        /** list<Link> Profile links. */
        public array $links
    ) {}
}
