<?php

namespace Larasell\Larasell\Exceptions;

use InvalidArgumentException;
use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;

/**
 * A rich text document that isn't in Larasell's rich text format, so it can't be saved or shown.
 */
final class InvalidRichText extends InvalidArgumentException implements StructuredException
{
    use HasStructuredException;

    public static function at(string $path, string $problem): self
    {
        return self::structured(
            errorCode: 'larasell.invalid-rich-text',
            message: "Invalid rich text at {$path}: {$problem}",
            why: 'Rich text is stored in its own JSON format, which allows only the blocks, marks and links storefronts can render safely.',
            fix: 'Send a document of version 1 with paragraph, heading (levels 2 to 4) and list blocks, bold, italic and underline marks, and absolute http, https or mailto links.',
        );
    }
}
