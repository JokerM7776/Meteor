<?php
declare(strict_types=1);
namespace App\Theme;

/** Theme API v1: ArchiveYear. */
final readonly class ArchiveYear
{
    public function __construct(
        /** Four-digit year. */
        public string $year,
        /** list<ArchiveMonth> Months with posts, newest first. */
        public array $months
    ) {}
}
