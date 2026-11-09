<?php

namespace Larasell\Larasell\Media;

/** What kind of media an item is, decided by its original file's MIME type on upload. */
enum MediaType: string
{
    case Image = 'image';
    case Video = 'video';

    /** MIME types a browser shows without plugins. SVGs are left out: they can carry scripts. */
    private const MIME_TYPES = [
        'image/jpeg' => self::Image,
        'image/png' => self::Image,
        'image/gif' => self::Image,
        'image/webp' => self::Image,
        'image/avif' => self::Image,
        'video/mp4' => self::Video,
        'video/webm' => self::Video,
        'video/quicktime' => self::Video,
    ];

    public static function forMimeType(string $mimeType): ?self
    {
        return self::MIME_TYPES[$mimeType] ?? null;
    }

    /** @return list<string> */
    public static function mimeTypes(): array
    {
        return array_keys(self::MIME_TYPES);
    }
}
