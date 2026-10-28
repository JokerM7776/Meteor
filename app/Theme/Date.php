<?php
declare(strict_types=1);
namespace App\Theme;

/** Theme API v1: Date. */
final readonly class Date
{
    public function __construct(
        /** Stored UTC timestamp, suitable for datetime attributes. */
        public string $iso,
        /** UTC date and time. */
        public \DateTimeImmutable $utc,
        /** Site-format localized date. */
        public string $long,
        /** Localized two-digit day. */
        public string $short,
        /** Localized month name. */
        public string $month,
        /** Localized time. */
        public string $time
    ) {}
}
