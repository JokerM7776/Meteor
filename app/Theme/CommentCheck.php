<?php
declare(strict_types=1);
namespace App\Theme;

/** Theme API v1: CommentCheck. */
final readonly class CommentCheck
{
    public function __construct(
        /** array<string,string|int> Preserved form fields. */
        public array $values,
        /** Error translation key. */
        public string $error,
        /** Form POST URL. */
        public string $action,
        /** Post URL. */
        public string $postUrl,
        /** Safe comment body HTML. */
        public string $html,
        /** CAPTCHA widget class. */
        public string $widget,
        /** Public CAPTCHA key. */
        public string $siteKey,
        /** CAPTCHA script URL. */
        public string $script
    ) {}
}
