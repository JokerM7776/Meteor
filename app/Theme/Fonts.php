<?php
declare(strict_types=1);
namespace App\Theme;

/** Theme API v1: Fonts. */
final readonly class Fonts
{
    public function __construct(
        /** Resolved font source: system, local or google. */
        public string $source,
        /** list<string> Font preload URLs. */
        public array $preloads,
        /** Font stylesheet URL or empty. */
        public string $cssUrl
    ) {}
}
