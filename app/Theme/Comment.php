<?php
declare(strict_types=1);
namespace App\Theme;

/** Theme API v1: Comment. */
final readonly class Comment
{
    public function __construct(
        /** Comment identifier. */
        public int $id,
        /** Author name. */
        public string $name,
        /** Validated author URL or empty. */
        public string $url,
        /** Safe comment body HTML. */
        public string $html,
        /** Creation date. */
        public Date $created,
        /** Comment by site owner. */
        public bool $owner,
        /** comment or webmention. */
        public string $kind,
        /** Webmention source URL. */
        public string $sourceUrl,
        /** Webmention reaction type. */
        public string $mentionType,
        /** list<Comment> Replies. */
        public array $replies
    ) {}
}
