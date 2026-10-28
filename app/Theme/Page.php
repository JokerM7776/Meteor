<?php
declare(strict_types=1);
namespace App\Theme;

/** Theme API v1: Page. */
final readonly class Page
{
    public function __construct(
        /** home, post, page, archive, tags, search, not_found or comment_check. */
        public string $type,
        /** Page title. */
        public string $title,
        /** Meta description. */
        public string $description,
        /** Absolute canonical URL. */
        public ?string $canonical,
        /** array<string,string> Absolute URLs keyed by hreflang. */
        public array $alternates,
        /** Prevent search indexing. */
        public bool $noindex,
        /** Image for link previews (og:image); null when there is none. */
        public ?Image $shareImage,
        /** Language of this page. */
        public Language $language,
        /** Post or page being shown; null on other pages. */
        public ?Post $post,
        /** list<Post> Home: the latest posts (pinned posts are in pinned). */
        public array $posts,
        /** list<Post> Home: pinned posts, when the Pinned section is on. */
        public array $pinned,
        /** Archive pages: page links; null elsewhere. */
        public ?Pagination $pagination,
        /** Category or tag archive: the chosen term; null elsewhere. */
        public ?Term $term,
        /** list<Term> Home and archive: categories with post counts. */
        public array $categories,
        /** list<Term> Archive: every tag with its post count (a post's own tags are in post->tags). */
        public array $tags,
        /** list<ArchiveYear> Archive posts on this page, grouped by year and month (site time zone). */
        public array $archive,
        /** Search query. */
        public string $query,
        /** Longest accepted search query, in characters. */
        public int $queryMaxLength,
        /** list<SearchResult> Search results. */
        public array $results,
        /** Post: comments, mentions and the form; null when comments are off and there are none. */
        public ?Comments $comments,
        /** array<string,Post|null> Post: "prev" (older) and "next" (newer) post in this language. */
        public array $neighbors,
        /** Home: the sections and intro from Settings → Home page; null elsewhere. */
        public ?Home $home,
        /** Post or page: table of contents HTML (already safe); empty when there is none. */
        public string $toc,
        /** not_found: the path that was asked for. */
        public string $requestedPath,
        /** Navigation key of this page: posts, tags, search or a page slug. */
        public string $active,
        /** Path after the language prefix, such as /posts/hello. */
        public string $suffix,
        /** Post: Webmention endpoint URL, or empty. */
        public string $webmention,
        /** comment_check: the CAPTCHA step of a comment; null elsewhere. */
        public ?CommentCheck $commentCheck
    ) {}
}
