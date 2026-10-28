<?php
declare(strict_types=1);
namespace App\Theme;

/** Theme API v1: Image. */
final readonly class Image
{
    public function __construct(
        /** Image URL. */
        public string $url,
        /** Pixel width, zero when unknown. */
        public int $width,
        /** Pixel height, zero when unknown. */
        public int $height,
        /** Alternative text. */
        public string $alt,
        /** Thumbnail URL when known. */
        public ?string $thumbUrl,
        /** MIME type, empty when unknown. */
        public string $type
    ) {}
}
