<?php

namespace Larasell\Larasell\Media\Exceptions;

use InvalidArgumentException;
use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use Larasell\Larasell\Media\MediaType;

final class UnsupportedMedia extends InvalidArgumentException implements StructuredException
{
    use HasStructuredException;

    public static function mimeType(string $fileName, string $mimeType): self
    {
        return self::structured(
            errorCode: 'larasell.media.unsupported',
            message: "The file [{$fileName}] is of the type [{$mimeType}], which is not an image or video media can hold.",
            why: 'Media holds images and videos browsers show on their own: '.implode(', ', MediaType::mimeTypes()).'.',
            fix: 'Convert the file to one of these types, or check it before uploading with MediaType::forMimeType().',
        );
    }

    public static function byProvider(string $provider, string $fileName, string $mimeType): self
    {
        return self::structured(
            errorCode: 'larasell.media.unsupported-by-provider',
            message: "The media provider [{$provider}] can't keep [{$fileName}], of the type [{$mimeType}].",
            why: 'Not every provider takes every kind of media; Mux only takes videos.',
            fix: 'Send it to another provider, e.g. with larasell.media.provider_for.image.',
        );
    }
}
