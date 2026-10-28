<?php
declare(strict_types=1);
namespace App\Theme;

/** Theme API v1: Post. */
final readonly class Post
{
    public function __construct(
        /** Content identifier. */
        public int $id,
        /** True for a static page. */
        public bool $isPage,
        /** Content slug. */
        public string $slug,
        /** Localized URL. */
        public string $url,
        /** Localized title. */
        public string $title,
        /** Plain text summary. */
        public string $summary,
        /** Safe compiled article HTML. */
        public string $html,
        /** Safe compiled table of contents HTML. */
        public string $toc,
        /** Estimated reading time. */
        public int $readingMinutes,
        /** Publication date; null for pages. */
        public ?Date $published,
        /** Last updated date when available. */
        public ?Date $updated,
        /** Cover image. */
        public ?Image $cover,
        /** Category when available. */
        public ?Term $category,
        /** list<Term> Tags when available. */
        public array $tags,
        /** list<Language> Published translations. */
        public array $translations,
        /** Pinned on the home page. */
        public bool $pinned,
        /** Comments enabled for this post. */
        public bool $commentsEnabled,
        /** Comment count when available. */
        public ?int $commentCount,
        /** Content language. */
        public Language $language
    ) {}
}
