<?php
declare(strict_types=1);
namespace App\Theme;

/** Theme API v1: Announcement. */
final readonly class Announcement
{
    public function __construct(
        /** Announcement title. */
        public string $title,
        /** Plain text message. */
        public string $message,
        /** Optional button label. */
        public string $buttonText,
        /** Optional button URL. */
        public string $buttonUrl,
        /** Whether the button is external. */
        public bool $external
    ) {}
}
