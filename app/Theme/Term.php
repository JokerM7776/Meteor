<?php
declare(strict_types=1);
namespace App\Theme;

/** Theme API v1: Term. */
final readonly class Term
{
    public function __construct(
        /** Term slug. */
        public string $slug,
        /** Localized term name. */
        public string $name,
        /** Archive URL. */
        public string $url,
        /** Published post count. */
        public int $count,
        /** Localized description, empty when unavailable. */
        public string $description
    ) {}
}
