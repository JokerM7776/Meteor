<?php
declare(strict_types=1);
namespace App\Theme;

/** Theme API v1: Language. */
final readonly class Language
{
    public function __construct(
        /** Locale code. */
        public string $code,
        /** Language name. */
        public string $name,
        /** Native language name. */
        public string $nativeName,
        /** ltr or rtl. */
        public string $dir,
        /** URL for this language on the current page. */
        public string $url,
        /** Whether this is the current language. */
        public bool $current,
        /** Whether only admins can see this language. */
        public bool $preview,
        /** Compact switcher label. */
        public string $shortLabel,
        /** Home page URL in this language. */
        public string $homeUrl,
        /** RSS feed URL in this language. */
        public string $feedUrl
    ) {}
}
