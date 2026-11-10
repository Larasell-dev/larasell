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
            why: 'Rich text is stored as Portable Text, limited to the styles, lists, decorators and links storefronts can render safely.',
            fix: 'Send a list of Portable Text blocks with style normal, h2, h3 or h4, flat bullet or number list items, the decorators strong, em and underline, and links to absolute http, https or mailto addresses.',
        );
    }
}
