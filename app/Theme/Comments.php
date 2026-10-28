<?php
declare(strict_types=1);
namespace App\Theme;

/** Theme API v1: Comments. */
final readonly class Comments
{
    public function __construct(
        /** list<Comment> Top-level comments. */
        public array $threads,
        /** list<Comment> Webmention reactions. */
        public array $reactions,
        /** Comment count. */
        public int $count,
        /** Whether the form is enabled. */
        public bool $open,
        /** array<string,string|int> Form fields: name, email, url, body, token, parent_id. */
        public array $values,
        /** Translation key for form error. */
        public string $error,
        /** Invalid field name. */
        public string $field,
        /** Reply target. */
        public ?Comment $replyTo,
        /** pending, published or empty. */
        public string $notice,
        /** Form POST URL. */
        public string $action,
        /** Post URL. */
        public string $url,
        /** Longest accepted comment, in characters. */
        public int $maxLength
    ) {}
}
