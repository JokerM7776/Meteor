<?php
declare(strict_types=1);
namespace App\Theme;

/** Theme API v1: Appearance. */
final readonly class Appearance
{
    public function __construct(
        /** auto, light or dark. */
        public string $scheme,
        /** Show scheme switcher. */
        public bool $switchEnabled,
        /** array<string,Link> Scheme links keyed by scheme. */
        public array $links,
        /** Appearance CSS URL or empty. */
        public string $accentCssUrl,
        /** Whether custom CSS is configured. */
        public bool $customCss
    ) {}
}
